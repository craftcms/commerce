<?php

namespace craft\commerce\services;

use craft\commerce\elements\Order;
use CraftCms\Commerce\Order\Adjuster\Contracts\AdjusterInterface;
use CraftCms\Commerce\Order\Models\OrderAdjustment;
use yii\base\Component;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\Order\OrderAdjustments::class)` instead.
 */
class OrderAdjustments extends Component
{
    public const EVENT_REGISTER_ORDER_ADJUSTERS = \CraftCms\Commerce\Order\OrderAdjustments::EVENT_REGISTER_ORDER_ADJUSTERS;

    public const EVENT_REGISTER_DISCOUNT_ADJUSTERS = \CraftCms\Commerce\Order\OrderAdjustments::EVENT_REGISTER_DISCOUNT_ADJUSTERS;

    /**
     * @return class-string<AdjusterInterface>[]
     */
    public function getAdjusters(): array
    {
        return app(\CraftCms\Commerce\Order\OrderAdjustments::class)->getAdjusters();
    }

    public function getOrderAdjustmentById(int $id): ?OrderAdjustment
    {
        return app(\CraftCms\Commerce\Order\OrderAdjustments::class)->getOrderAdjustmentById($id);
    }

    /**
     * @return OrderAdjustment[]
     */
    public function getAllOrderAdjustmentsByOrderId(int $orderId): array
    {
        return app(\CraftCms\Commerce\Order\OrderAdjustments::class)->getAllOrderAdjustmentsByOrderId($orderId);
    }

    public function saveOrderAdjustment(OrderAdjustment $orderAdjustment, bool $runValidation = true): bool
    {
        return app(\CraftCms\Commerce\Order\OrderAdjustments::class)->saveOrderAdjustment($orderAdjustment, $runValidation);
    }

    public function deleteAllOrderAdjustmentsByOrderId(int $orderId): bool
    {
        return app(\CraftCms\Commerce\Order\OrderAdjustments::class)->deleteAllOrderAdjustmentsByOrderId($orderId);
    }

    public function deleteOrderAdjustmentByAdjustmentId(int $adjustmentId): bool
    {
        return app(\CraftCms\Commerce\Order\OrderAdjustments::class)->deleteOrderAdjustmentByAdjustmentId($adjustmentId);
    }

    /**
     * @param Order[] $orders
     * @return Order[]
     */
    public function eagerLoadOrderAdjustmentsForOrders(array $orders): array
    {
        return app(\CraftCms\Commerce\Order\OrderAdjustments::class)->eagerLoadOrderAdjustmentsForOrders($orders);
    }

    /**
     * @return class-string<AdjusterInterface>[]
     */
    public function getDiscountAdjusters(): array
    {
        return app(\CraftCms\Commerce\Order\OrderAdjustments::class)->getDiscountAdjusters();
    }
}
