<?php

namespace craft\commerce\services;

use craft\commerce\elements\Order;
use craft\elements\User;
use craft\events\ConfigEvent;
use craft\events\DefineElementDeletionBlockersEvent;
use craft\events\ModelEvent;
use yii\base\Component;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\Services\Orders::class)` instead.
 */
class Orders extends Component
{
    public const CONFIG_FIELDLAYOUT_KEY = \CraftCms\Commerce\Services\Orders::CONFIG_FIELDLAYOUT_KEY;

    public function handleChangedFieldLayout(ConfigEvent $event): void
    {
        app(\CraftCms\Commerce\Services\Orders::class)->handleChangedFieldLayout($event);
    }

    public function handleDeletedFieldLayout(): void
    {
        app(\CraftCms\Commerce\Services\Orders::class)->handleDeletedFieldLayout();
    }

    public function getOrderById(int $id): ?Order
    {
        return app(\CraftCms\Commerce\Services\Orders::class)->getOrderById($id);
    }

    public function getOrderByNumber(string $number): ?Order
    {
        return app(\CraftCms\Commerce\Services\Orders::class)->getOrderByNumber($number);
    }

    /**
     * @return Order[]|null
     */
    public function getOrdersByCustomer(User|int $customer): ?array
    {
        return app(\CraftCms\Commerce\Services\Orders::class)->getOrdersByCustomer($customer);
    }

    /**
     * @return Order[]|null
     */
    public function getOrdersByEmail(string $email): ?array
    {
        return app(\CraftCms\Commerce\Services\Orders::class)->getOrdersByEmail($email);
    }

    /**
     * @param Order[] $orders
     * @return Order[]
     */
    public function eagerLoadAddressesForOrders(array $orders): array
    {
        return app(\CraftCms\Commerce\Services\Orders::class)->eagerLoadAddressesForOrders($orders);
    }

    public function beforeDeleteUserHandler(DefineElementDeletionBlockersEvent $event): void
    {
        app(\CraftCms\Commerce\Services\Orders::class)->beforeDeleteUserHandler($event);
    }

    /**
     * @param int|int[] $oldUserId
     */
    public function reassignOrders(int|array $oldUserId, int $newUserId): int
    {
        return app(\CraftCms\Commerce\Services\Orders::class)->reassignOrders($oldUserId, $newUserId);
    }

    /**
     * @param int|int[] $orderIds
     */
    public function removeCustomerData(int|array $orderIds, array $dataToRemove = ['customerId', 'email']): int
    {
        return app(\CraftCms\Commerce\Services\Orders::class)->removeCustomerData($orderIds, $dataToRemove);
    }

    public function afterSaveAddressHandler(ModelEvent $event): void
    {
        app(\CraftCms\Commerce\Services\Orders::class)->afterSaveAddressHandler($event);
    }
}
