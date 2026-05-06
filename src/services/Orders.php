<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\db\Table;
use craft\commerce\elements\deletionblockers\OrderCustomersDeletionBlocker;
use craft\commerce\elements\Order;
use craft\db\Query;
use craft\elements\Address;
use craft\elements\User;
use craft\errors\ElementNotFoundException;
use craft\errors\InvalidElementException;
use craft\errors\UnsupportedSiteException;
use craft\events\ConfigEvent;
use craft\events\DefineElementDeletionBlockersEvent;
use craft\events\ModelEvent;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;
use craft\helpers\ProjectConfig as ProjectConfigHelper;
use craft\models\FieldLayout;
use http\Exception\InvalidArgumentException;
use yii\base\Component;
use yii\base\Exception;

/**
 * Orders service.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Orders extends Component
{
    public const CONFIG_FIELDLAYOUT_KEY = 'commerce.orders.fieldLayouts';

    /**
     * Handle field layout change
     *
     * @throws Exception
     */
    public function handleChangedFieldLayout(ConfigEvent $event): void
    {
        $data = $event->newValue;

        ProjectConfigHelper::ensureAllFieldsProcessed();
        $fieldsService = Craft::$app->getFields();

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
     * Handle field layout being deleted
     */
    public function handleDeletedFieldLayout(): void
    {
        Craft::$app->getFields()->deleteLayoutsByType(Order::class);
    }

    /**
     * Get an order by its ID.
     *
     * @param int $id
     * @return ?Order
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
     * @param int|User $customer
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
     * @param array|Order[] $orders
     * @return Order[]
     * @since 4.0.0
     */
    public function eagerLoadAddressesForOrders(array $orders): array
    {
        $shippingAddressIds = array_filter(ArrayHelper::getColumn($orders, 'shippingAddressId'));
        $billingAddressIds = array_filter(ArrayHelper::getColumn($orders, 'billingAddressId'));
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
     *
     * @param DefineElementDeletionBlockersEvent $event the event.
     */
    public function beforeDeleteUserHandler(DefineElementDeletionBlockersEvent $event): void
    {
        $event->blockers[] = new OrderCustomersDeletionBlocker($event->elements, $event->hardDelete);
    }

    /**
     * Reassigns orders to a new customer.
     *
     * @param int|int[] $oldUserId
     * @param int $newUserId
     * @return int The number of affected orders
     * @throws \yii\db\Exception
     * @since 5.7.0
     */
    public function reassignOrders(int|array $oldUserId, int $newUserId): int
    {
        $newUserEmail = (new Query())
            ->select(['email'])
            ->from(\craft\db\Table::USERS)
            ->where(['id' => $newUserId])
            ->scalar();

        if (!$newUserEmail) {
            throw new InvalidArgumentException('Unable to reassign user id: ' . $newUserId);
        }

        $count = Db::update(Table::ORDERS, [
            'customerId' => $newUserId,
            'email' => $newUserEmail,
        ], [
            'customerId' => $oldUserId,
        ], [], false);

        // Invalidate all order caches
        Craft::$app->getElements()->invalidateCachesForElementType(Order::class);

        return $count;
    }

    /**
     * @param int|array $orderId
     * @param array $dataToRemove
     * @return int
     * @throws \yii\db\Exception
     * @since 5.7.0
     */
    public function removeCustomerData(int|array $orderId, array $dataToRemove = ['customerId', 'email']): int
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

        $count = Db::update(Table::ORDERS, $data, [
            'id' => $orderId,
        ], [], false);

        Craft::$app->getElements()->invalidateCachesForElementType(Order::class);

        return $count;
    }

    /**
     * @param ModelEvent $event
     * @return void
     * @throws Exception
     * @throws \Throwable
     * @throws ElementNotFoundException
     * @throws InvalidElementException
     * @throws UnsupportedSiteException
     * @since 4.2.11
     */
    public function afterSaveAddressHandler(ModelEvent $event): void
    {

        /** @var Address $address */
        $address = $event->sender;
        if ($address->getIsDraft()) {
            return;
        }

        // Find all orders using this address as a source
        $idQuery = (new Query())
            ->select(['id'])
            ->from(Table::ORDERS)
            ->where(['sourceBillingAddressId' => $address->id])
            ->orWhere(['sourceShippingAddressId' => $address->id]);

        /** @var Order[] $carts */
        $carts = Order::find()
            ->where(['commerce_orders.id' => $idQuery])
            ->isCompleted(false)
            ->all();

        if (empty($carts)) {
            return;
        }

        foreach ($carts as $cart) {
            // Update the billing address
            if ($cart->sourceBillingAddressId === $address->id) {
                $newBillingAddress = Craft::$app->getElements()->duplicateElement($address, [
                    'primaryOwner' => $cart,
                    'owner' => $cart,
                    'title' => Craft::t('commerce', 'Billing Address'),
                ]);
                $cart->billingAddressId = $newBillingAddress->id;
            }

            // Update the shipping address
            if ($cart->sourceShippingAddressId === $address->id) {
                $newShippingAddress = Craft::$app->getElements()->duplicateElement($address, [
                    'primaryOwner' => $cart,
                    'owner' => $cart,
                    'title' => Craft::t('commerce', 'Shipping Address'),
                ]);
                $cart->shippingAddressId = $newShippingAddress->id;
            }

            // Save the cart to trigger events and recalculations.
            Craft::$app->getElements()->saveElement($cart, false);
        }
    }
}
