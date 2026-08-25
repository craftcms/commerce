<?php

namespace craft\commerce\services;

use CraftCms\Commerce\Order\Elements\Order;
use craft\elements\User;
use craft\events\ConfigEvent;
use yii\base\Component;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\Order\Orders::class)` instead.
 */
class Orders extends Component
{
    public const CONFIG_FIELDLAYOUT_KEY = \CraftCms\Commerce\Order\Orders::CONFIG_FIELDLAYOUT_KEY;

    public function handleChangedFieldLayout(ConfigEvent $event): void
    {
        app(\CraftCms\Commerce\Order\Orders::class)->handleChangedFieldLayout($event);
    }

    public function handleDeletedFieldLayout(): void
    {
        app(\CraftCms\Commerce\Order\Orders::class)->handleDeletedFieldLayout();
    }

    public function getOrderById(int $id): ?Order
    {
        return app(\CraftCms\Commerce\Order\Orders::class)->getOrderById($id);
    }

    public function getOrderByNumber(string $number): ?Order
    {
        return app(\CraftCms\Commerce\Order\Orders::class)->getOrderByNumber($number);
    }

    /**
     * @return Order[]|null
     */
    public function getOrdersByCustomer(User|int $customer): ?array
    {
        return app(\CraftCms\Commerce\Order\Orders::class)->getOrdersByCustomer($customer);
    }

    /**
     * @return Order[]|null
     */
    public function getOrdersByEmail(string $email): ?array
    {
        return app(\CraftCms\Commerce\Order\Orders::class)->getOrdersByEmail($email);
    }

    /**
     * @param Order[] $orders
     * @return Order[]
     */
    public function eagerLoadAddressesForOrders(array $orders): array
    {
        return app(\CraftCms\Commerce\Order\Orders::class)->eagerLoadAddressesForOrders($orders);
    }

    /**
     * @param int|int[] $oldUserId
     */
    public function reassignOrders(int|array $oldUserId, int $newUserId): int
    {
        return app(\CraftCms\Commerce\Order\Orders::class)->reassignOrders($oldUserId, $newUserId);
    }

    /**
     * @param int|int[] $orderIds
     */
    public function removeCustomerData(int|array $orderIds, array $dataToRemove = ['customerId', 'email']): int
    {
        return app(\CraftCms\Commerce\Order\Orders::class)->removeCustomerData($orderIds, $dataToRemove);
    }
}
