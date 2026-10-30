<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Override;

/**
 * @method static bool canUseSales()
 * @method static \CraftCms\Commerce\Promotion\Data\Sale|null getSaleById(int $id)
 * @method static \CraftCms\Commerce\Promotion\Data\Sale[] getAllSales()
 * @method static \CraftCms\Commerce\Promotion\Data\Sale[] getSalesForPurchasable(\CraftCms\Commerce\Purchasable\Contracts\PurchasableInterface $purchasable, \CraftCms\Commerce\Order\Elements\Order|null $order = null)
 * @method static \CraftCms\Commerce\Promotion\Data\Sale[] getSalesRelatedToPurchasable(\CraftCms\Commerce\Purchasable\Contracts\PurchasableInterface $purchasable)
 * @method static float getSalePriceForPurchasable(\CraftCms\Commerce\Purchasable\Contracts\PurchasableInterface $purchasable, \CraftCms\Commerce\Order\Elements\Order|null $order = null)
 * @method static bool matchPurchasableAndSale(\CraftCms\Commerce\Purchasable\Contracts\PurchasableInterface $purchasable, \CraftCms\Commerce\Promotion\Data\Sale $sale, \CraftCms\Commerce\Order\Elements\Order|null $order = null)
 * @method static bool saveSale(\CraftCms\Commerce\Promotion\Data\Sale $model, bool $runValidation = true)
 * @method static bool reorderSales(array $ids)
 * @method static bool deleteSaleById(int $id)
 *
 * @see \CraftCms\Commerce\Promotion\Sales
 */
class Sales extends Facade
{
    #[Override]
    protected static function getFacadeAccessor(): string
    {
        return \CraftCms\Commerce\Promotion\Sales::class;
    }
}
