<?php

namespace craft\commerce\services;

use CraftCms\Commerce\Order\Elements\Order;
use craft\events\ConfigEvent;
use CraftCms\Commerce\Email\Events\EmailEvent;
use CraftCms\Commerce\Order\Data\OrderHistory;
use CraftCms\Commerce\Order\Data\OrderStatus;
use Illuminate\Support\Collection;
use Throwable;
use yii\base\Component;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\Order\OrderStatuses::class)` instead.
 */
class OrderStatuses extends Component
{
    public const EVENT_DEFAULT_ORDER_STATUS = \CraftCms\Commerce\Order\OrderStatuses::EVENT_DEFAULT_ORDER_STATUS;

    public const EVENT_ORDER_STATUS_CHANGE_EMAILS = \CraftCms\Commerce\Order\OrderStatuses::EVENT_ORDER_STATUS_CHANGE_EMAILS;

    public const CONFIG_STATUSES_KEY = \CraftCms\Commerce\Order\OrderStatuses::CONFIG_STATUSES_KEY;

    /**
     * @return Collection<int, OrderStatus>
     */
    public function getAllOrderStatuses(?int $storeId = null, bool $withTrashed = false): Collection
    {
        return app(\CraftCms\Commerce\Order\OrderStatuses::class)->getAllOrderStatuses($storeId, $withTrashed);
    }

    public function getOrderStatusById(int $id, ?int $storeId = null): ?OrderStatus
    {
        return app(\CraftCms\Commerce\Order\OrderStatuses::class)->getOrderStatusById($id, $storeId);
    }

    public function getOrderStatusByUid(string $uid, ?int $storeId = null): ?OrderStatus
    {
        return app(\CraftCms\Commerce\Order\OrderStatuses::class)->getOrderStatusByUid($uid, $storeId);
    }

    public function getOrderStatusByHandle(string $handle, ?int $storeId = null): ?OrderStatus
    {
        return app(\CraftCms\Commerce\Order\OrderStatuses::class)->getOrderStatusByHandle($handle, $storeId);
    }

    public function getDefaultOrderStatus(?int $storeId = null): ?OrderStatus
    {
        return app(\CraftCms\Commerce\Order\OrderStatuses::class)->getDefaultOrderStatus($storeId);
    }

    public function getDefaultOrderStatusId(?int $storeId = null): ?int
    {
        return app(\CraftCms\Commerce\Order\OrderStatuses::class)->getDefaultOrderStatusId($storeId);
    }

    public function getDefaultOrderStatusForOrder(Order $order): ?OrderStatus
    {
        return app(\CraftCms\Commerce\Order\OrderStatuses::class)->getDefaultOrderStatusForOrder($order);
    }

    public function getOrderCountByStatus(?int $storeId = null): array
    {
        return app(\CraftCms\Commerce\Order\OrderStatuses::class)->getOrderCountByStatus($storeId);
    }

    public function saveOrderStatus(OrderStatus $orderStatus, array $emailIds = [], bool $runValidation = true, bool $force = false): bool
    {
        return app(\CraftCms\Commerce\Order\OrderStatuses::class)->saveOrderStatus($orderStatus, $emailIds, $runValidation, $force);
    }

    /**
     * @throws Throwable if reasons
     */
    public function handleChangedOrderStatus(ConfigEvent $event): void
    {
        app(\CraftCms\Commerce\Order\OrderStatuses::class)->handleChangedOrderStatus($event);
    }

    /**
     * @throws Throwable
     */
    public function deleteOrderStatusById(int $id, ?int $storeId = null): bool
    {
        return app(\CraftCms\Commerce\Order\OrderStatuses::class)->deleteOrderStatusById($id, $storeId);
    }

    /**
     * @throws Throwable if reasons
     */
    public function handleDeletedOrderStatus(ConfigEvent $event): void
    {
        app(\CraftCms\Commerce\Order\OrderStatuses::class)->handleDeletedOrderStatus($event);
    }

    public function pruneDeletedEmail(EmailEvent $event): void
    {
        app(\CraftCms\Commerce\Order\OrderStatuses::class)->pruneDeletedEmail($event);
    }

    public function statusChangeHandler(Order $order, OrderHistory $orderHistory): void
    {
        app(\CraftCms\Commerce\Order\OrderStatuses::class)->statusChangeHandler($order, $orderHistory);
    }

    /**
     * @param int[] $ids
     */
    public function reorderOrderStatuses(array $ids): bool
    {
        return app(\CraftCms\Commerce\Order\OrderStatuses::class)->reorderOrderStatuses($ids);
    }
}
