<?php

namespace craft\commerce\services;

use craft\commerce\elements\Order;
use CraftCms\Commerce\Order\Models\OrderHistory;
use yii\base\Component;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\Services\OrderHistories::class)` instead.
 */
class OrderHistories extends Component
{
    public const EVENT_ORDER_STATUS_CHANGE = \CraftCms\Commerce\Services\OrderHistories::EVENT_ORDER_STATUS_CHANGE;

    public function getOrderHistoryById(int $id): ?OrderHistory
    {
        return app(\CraftCms\Commerce\Services\OrderHistories::class)->getOrderHistoryById($id);
    }

    /**
     * @return OrderHistory[]
     */
    public function getAllOrderHistoriesByOrderId(int $id): array
    {
        return app(\CraftCms\Commerce\Services\OrderHistories::class)->getAllOrderHistoriesByOrderId($id);
    }

    public function createOrderHistoryFromOrder(Order $order, ?int $oldStatusId): bool
    {
        return app(\CraftCms\Commerce\Services\OrderHistories::class)->createOrderHistoryFromOrder($order, $oldStatusId);
    }

    public function saveOrderHistory(OrderHistory $model, bool $runValidation = true): bool
    {
        return app(\CraftCms\Commerce\Services\OrderHistories::class)->saveOrderHistory($model, $runValidation);
    }

    public function deleteOrderHistoryById(int $id): bool
    {
        return app(\CraftCms\Commerce\Services\OrderHistories::class)->deleteOrderHistoryById($id);
    }
}
