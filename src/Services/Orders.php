<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Services;

use craft\commerce\elements\deletionblockers\OrderCustomersDeletionBlocker;
use craft\commerce\elements\Order;
use CraftCms\Cms\Address\Elements\Address;
use CraftCms\Cms\User\Elements\User;
use craft\events\ConfigEvent;
use craft\events\DefineElementDeletionBlockersEvent;
use craft\events\ModelEvent;
use CraftCms\Cms\FieldLayout\FieldLayout;
use CraftCms\Cms\Database\Table as CraftTable;
use CraftCms\Cms\ProjectConfig\ProjectConfigHelper;
use CraftCms\Commerce\Database\Table;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Facades\DB;
use yii\base\InvalidArgumentException;
use function CraftCms\Cms\t;

#[Singleton]
class Orders
{
    public const string CONFIG_FIELDLAYOUT_KEY = 'commerce.orders.fieldLayouts';

    /**
     * Handle field layout change.
     */
    public function handleChangedFieldLayout(ConfigEvent $event): void
    {
        $data = $event->newValue;

        ProjectConfigHelper::ensureAllFieldsProcessed();
        $fieldsService = \Craft::$app->getFields();

        if (empty($data) || empty(reset($data))) {
            // Delete the field layout
            $fieldsService->deleteLayoutsByType(Order::class);
            return;
        }

        // Save the field layout
        $layout = FieldLayout::createFromConfig(reset($data));
        $layout->id = $fieldsService->getLayoutByType(Order::class)->id;
        $layout->type = Order::class;
        $layout->uid = key($data);
        $fieldsService->saveLayout($layout, false);
    }

    /**
     * Handle field layout being deleted.
     */
    public function handleDeletedFieldLayout(): void
    {
        \Craft::$app->getFields()->deleteLayoutsByType(Order::class);
    }

    /**
     * Get an order by its ID.
     */
    public function getOrderById(int $id): ?Order
    {
        if (!$id) {
            return null;
        }

        return Order::find()->id($id)->status(null)->one();
    }

    /**
     * Get an order by its number.
     */
    public function getOrderByNumber(string $number): ?Order
    {
        return Order::find()->number($number)->one();
    }

    /**
     * Get all orders by their customer.
     *
     * @return Order[]|null
     */
    public function getOrdersByCustomer(User|int $customer): ?array
    {
        if (!$customer) {
            return null;
        }

        $query = Order::find();
        if ($customer instanceof User) {
            $query->customer($customer);
        } else {
            $query->customerId($customer);
        }
        $query->isCompleted();
        $query->limit(null);

        return $query->all();
    }

    /**
     * Get all orders by their email.
     *
     * @return Order[]|null
     */
    public function getOrdersByEmail(string $email): ?array
    {
        return Order::find()->email($email)->isCompleted()->limit(null)->all();
    }

    /**
     * @param Order[] $orders
     * @return Order[]
     */
    public function eagerLoadAddressesForOrders(array $orders): array
    {
        $shippingAddressIds = collect($orders)->pluck('shippingAddressId')->filter()->all();
        $billingAddressIds = collect($orders)->pluck('billingAddressId')->filter()->all();
        $ids = array_unique(array_merge($shippingAddressIds, $billingAddressIds));

        // Query addresses as array to avoid instantiating elements immediately
        $query = Address::find()
            ->id($ids)
            ->indexBy('id')
            ->asArray();
        /** @var array $addresses */
        $addresses = $query->all();

        foreach ($orders as $key => $order) {
            if (isset($order['shippingAddressId'], $addresses[$order['shippingAddressId']])) {
                $data = $addresses[$order['shippingAddressId']];
                $data['owner'] = $order;
                /** @var Address $address */
                $address = $query->createElement($data);

                $order->setShippingAddress($address);
            }

            if (isset($order['billingAddressId'], $addresses[$order['billingAddressId']])) {
                $data = $addresses[$order['billingAddressId']];
                $data['owner'] = $order;

                /** @var Address $address */
                $address = $query->createElement($data);

                $order->setBillingAddress($address);
            }

            $orders[$key] = $order;
        }

        return $orders;
    }

    /**
     * Prevent deleting a user if they have any orders.
     */
    public function beforeDeleteUserHandler(DefineElementDeletionBlockersEvent $event): void
    {
        $event->blockers[] = new OrderCustomersDeletionBlocker($event->elements, $event->hardDelete);
    }

    /**
     * Reassigns orders to a new customer.
     *
     * @param int|int[] $oldUserId
     * @return int The number of affected orders
     */
    public function reassignOrders(int|array $oldUserId, int $newUserId): int
    {
        $newUserEmail = DB::table(CraftTable::USERS)
            ->where('id', $newUserId)
            ->value('email');

        if (!$newUserEmail) {
            throw new InvalidArgumentException('Unable to reassign user id: ' . $newUserId);
        }

        $count = DB::table(Table::ORDERS)
            ->where('customerId', $oldUserId)
            ->update([
                'customerId' => $newUserId,
                'email' => $newUserEmail,
            ]);

        // Invalidate all order caches
        \Craft::$app->getElements()->invalidateCachesForElementType(Order::class);

        return $count;
    }

    /**
     * @param int|int[] $orderIds
     */
    public function removeCustomerData(int|array $orderIds, array $dataToRemove = ['customerId', 'email']): int
    {
        $allowedRemovalKeys = [
            'customerId',
            'email',
            'billingAddressId',
            'shippingAddressId',
            'orderCompletedEmail',
        ];

        $data = [];
        foreach ($dataToRemove as $key) {
            if (!in_array($key, $allowedRemovalKeys)) {
                continue;
            }

            // Make sure we are setting the `customerDeleted` flag when removing the `customerId`
            if ($key === 'customerId') {
                $data['customerDeleted'] = true;
            }

            $data[$key] = null;
        }

        $count = DB::table(Table::ORDERS)
            ->whereIn('id', (array)$orderIds)
            ->update($data);

        \Craft::$app->getElements()->invalidateCachesForElementType(Order::class);

        return $count;
    }

    public function afterSaveAddressHandler(ModelEvent $event): void
    {
        /** @var Address $address */
        $address = $event->sender;
        if ($address->getIsDraft()) {
            return;
        }

        // Find all orders using this address as a source
        $ids = DB::table(Table::ORDERS)
            ->select('id')
            ->where('sourceBillingAddressId', $address->id)
            ->orWhere('sourceShippingAddressId', $address->id)
            ->pluck('id')
            ->all();

        /** @var Order[] $carts */
        $carts = Order::find()
            ->where(['commerce_orders.id' => $ids])
            ->isCompleted(false)
            ->all();

        if (empty($carts)) {
            return;
        }

        foreach ($carts as $cart) {
            // Update the billing address
            if ($cart->sourceBillingAddressId === $address->id) {
                $newBillingAddress = \Craft::$app->getElements()->duplicateElement($address, [
                    'primaryOwner' => $cart,
                    'owner' => $cart,
                    'title' => t('Billing Address', category: 'commerce'),
                ]);
                $cart->billingAddressId = $newBillingAddress->id;
            }

            // Update the shipping address
            if ($cart->sourceShippingAddressId === $address->id) {
                $newShippingAddress = \Craft::$app->getElements()->duplicateElement($address, [
                    'primaryOwner' => $cart,
                    'owner' => $cart,
                    'title' => t('Shipping Address', category: 'commerce'),
                ]);
                $cart->shippingAddressId = $newShippingAddress->id;
            }

            // Save the cart to trigger events and recalculations.
            \Craft::$app->getElements()->saveElement($cart, false);
        }
    }
}
