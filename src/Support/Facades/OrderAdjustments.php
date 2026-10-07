<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Override;

/**
 * @method static string[] getAdjusters()
 * @method static \CraftCms\Commerce\Order\Data\OrderAdjustment|null getOrderAdjustmentById(int $id)
 * @method static \CraftCms\Commerce\Order\Data\OrderAdjustment[] getAllOrderAdjustmentsByOrderId(int $orderId)
 * @method static bool saveOrderAdjustment(\CraftCms\Commerce\Order\Data\OrderAdjustment $orderAdjustment, bool $runValidation = true)
 * @method static bool deleteAllOrderAdjustmentsByOrderId(int $orderId)
 * @method static bool deleteOrderAdjustmentByAdjustmentId(int $adjustmentId)
 * @method static \CraftCms\Commerce\Order\Elements\Order[] eagerLoadOrderAdjustmentsForOrders(\CraftCms\Commerce\Order\Elements\Order[] $orders)
 * @method static string[] getDiscountAdjusters()
 *
 * @see \CraftCms\Commerce\Order\OrderAdjustments
 */
class OrderAdjustments extends Facade
{
    #[Override]
    protected static function getFacadeAccessor(): string
    {
        return \CraftCms\Commerce\Order\OrderAdjustments::class;
    }
}
