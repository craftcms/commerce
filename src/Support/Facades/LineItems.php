<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Override;

/**
 * @method static \CraftCms\Commerce\Order\LineItem\Data\LineItem[] getAllLineItemsByOrderId(int $orderId)
 * @method static \CraftCms\Commerce\Order\LineItem\Data\LineItem resolveLineItem(\CraftCms\Commerce\Order\Elements\Order $order, int $purchasableId, array $options = [], array $params = [])
 * @method static \CraftCms\Commerce\Order\LineItem\Data\LineItem resolveCustomLineItem(\CraftCms\Commerce\Order\Elements\Order $order, string $sku, array $options = [])
 * @method static bool saveLineItem(\CraftCms\Commerce\Order\LineItem\Data\LineItem $lineItem, bool $runValidation = true)
 * @method static \CraftCms\Commerce\Order\LineItem\Data\LineItem|null getLineItemById(int $id)
 * @method static \CraftCms\Commerce\Order\LineItem\Data\LineItem create(\CraftCms\Commerce\Order\Elements\Order $order, array $params = [], \CraftCms\Commerce\Order\LineItem\Enums\LineItemType $type = 'purchasable')
 * @method static bool deleteAllLineItemsByOrderId(int $orderId)
 * @method static \CraftCms\Commerce\Order\Elements\Order[] eagerLoadLineItemsForOrders(\CraftCms\Commerce\Order\Elements\Order[] $orders)
 * @method static void orderCompleteHandler(\CraftCms\Commerce\Order\LineItem\Data\LineItem $lineItem, \CraftCms\Commerce\Order\Elements\Order $order)
 *
 * @see \CraftCms\Commerce\Order\LineItem\LineItems
 */
class LineItems extends Facade
{
    #[Override]
    protected static function getFacadeAccessor(): string
    {
        return \CraftCms\Commerce\Order\LineItem\LineItems::class;
    }
}
