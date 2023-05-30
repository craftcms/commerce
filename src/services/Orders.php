<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\elements\Order;
use craft\elements\Address;
use craft\elements\User;
use craft\events\ConfigEvent;
use craft\events\ModelEvent;
use craft\helpers\ArrayHelper;
use craft\helpers\ProjectConfig as ProjectConfigHelper;
use craft\models\FieldLayout;
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
     * @deprecated in 3.4.17. Unused fields will be pruned automatically as field layouts are resaved.
     */
    public function pruneDeletedField(): void
    {
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
     * @return Order|null
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

        /** @var Address[] $addresses */
        $addresses = Address::find()->id($ids)->indexBy('id')->all();

        foreach ($orders as $key => $order) {
            if (isset($order['shippingAddressId'], $addresses[$order['shippingAddressId']])) {
                $order->setShippingAddress($addresses[$order['shippingAddressId']]);
            }

            if (isset($order['billingAddressId'], $addresses[$order['billingAddressId']])) {
                $order->setBillingAddress($addresses[$order['billingAddressId']]);
            }

            $orders[$key] = $order;
        }

        return $orders;
    }

    /**
     * Prevent deleting a user if they have any orders.
     *
     * @param ModelEvent $event the event.
     */
    public function beforeDeleteUserHandler(ModelEvent $event): void
    {
        /** @var User $user */
        $user = $event->sender;

        // If there are any orders, make sure that this is not allowed.
        if (Order::find()->customerId($user->id)->status(null)->exists()) {
            // TODO revise this stop-gap measure when Craft CMS gets a way to hook into the user delete process.
            throw new Exception("Unable to delete a user with an existing order. User ID: “{$user->id}”");
        }
    }
}
