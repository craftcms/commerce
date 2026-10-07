<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Override;

/**
 * @method static \CraftCms\Commerce\Order\Data\OrderHistory|null getOrderHistoryById(int $id)
 * @method static \CraftCms\Commerce\Order\Data\OrderHistory[] getAllOrderHistoriesByOrderId(int $id)
 * @method static bool createOrderHistoryFromOrder(\CraftCms\Commerce\Order\Elements\Order $order, int|null $oldStatusId)
 * @method static bool saveOrderHistory(\CraftCms\Commerce\Order\Data\OrderHistory $model, bool $runValidation = true)
 * @method static bool deleteOrderHistoryById(int $id)
 *
 * @see \CraftCms\Commerce\Order\OrderHistories
 */
class OrderHistories extends Facade
{
    #[Override]
    protected static function getFacadeAccessor(): string
    {
        return \CraftCms\Commerce\Order\OrderHistories::class;
    }
}
