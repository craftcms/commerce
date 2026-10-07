<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Override;

/**
 * @method static \CraftCms\Commerce\Order\Elements\Order[] eagerLoadOrderNoticesForOrders(\CraftCms\Commerce\Order\Elements\Order[] $orders)
 *
 * @see \CraftCms\Commerce\Order\OrderNotices
 */
class OrderNotices extends Facade
{
    #[Override]
    protected static function getFacadeAccessor(): string
    {
        return \CraftCms\Commerce\Order\OrderNotices::class;
    }
}
