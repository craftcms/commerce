<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Override;

/**
 * @method static \Illuminate\Support\Collection getAllOrderStatuses(int|null $storeId = null, bool $withTrashed = false)
 * @method static \CraftCms\Commerce\Order\Data\OrderStatus|null getOrderStatusById(int $id, int|null $storeId = null)
 * @method static \CraftCms\Commerce\Order\Data\OrderStatus|null getOrderStatusByUid(string $uid, int|null $storeId = null)
 * @method static \CraftCms\Commerce\Order\Data\OrderStatus|null getOrderStatusByHandle(string $handle, int|null $storeId = null)
 * @method static \CraftCms\Commerce\Order\Data\OrderStatus|null getDefaultOrderStatus(int|null $storeId = null)
 * @method static int|null getDefaultOrderStatusId(int|null $storeId = null)
 * @method static \CraftCms\Commerce\Order\Data\OrderStatus|null getDefaultOrderStatusForOrder(\CraftCms\Commerce\Order\Elements\Order $order)
 * @method static array getOrderCountByStatus(int|null $storeId = null)
 * @method static bool saveOrderStatus(\CraftCms\Commerce\Order\Data\OrderStatus $orderStatus, array $emailIds = [], bool $runValidation = true, bool $force = false)
 * @method static void handleChangedOrderStatus(\CraftCms\Cms\ProjectConfig\Events\ConfigEvent $event)
 * @method static bool deleteOrderStatusById(int $id, int|null $storeId = null)
 * @method static void handleDeletedOrderStatus(\CraftCms\Cms\ProjectConfig\Events\ConfigEvent $event)
 * @method static void pruneDeletedEmail(\CraftCms\Commerce\Email\Events\EmailEvent $event)
 * @method static void statusChangeHandler(\CraftCms\Commerce\Order\Elements\Order $order, \CraftCms\Commerce\Order\Data\OrderHistory $orderHistory)
 * @method static bool reorderOrderStatuses(int[] $ids)
 *
 * @see \CraftCms\Commerce\Order\OrderStatuses
 */
class OrderStatuses extends Facade
{
    #[Override]
    protected static function getFacadeAccessor(): string
    {
        return \CraftCms\Commerce\Order\OrderStatuses::class;
    }
}
