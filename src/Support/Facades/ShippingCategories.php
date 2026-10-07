<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Override;

/**
 * @method static \Illuminate\Support\Collection getAllShippingCategories(int|null $storeId = null, bool $withTrashed = false)
 * @method static array getAllShippingCategoriesAsList(int|null $storeId = null)
 * @method static \CraftCms\Commerce\Shipping\Data\ShippingCategory|null getShippingCategoryById(int $shippingCategoryId, int|null $storeId = null)
 * @method static \CraftCms\Commerce\Shipping\Data\ShippingCategory|null getShippingCategoryByHandle(string $shippingCategoryHandle, int|null $storeId = null)
 * @method static \CraftCms\Commerce\Shipping\Data\ShippingCategory getDefaultShippingCategory(int $storeId)
 * @method static bool saveShippingCategory(\CraftCms\Commerce\Shipping\Data\ShippingCategory $shippingCategory, bool $runValidation = true)
 * @method static bool deleteShippingCategoryById(int $id)
 * @method static array getShippingCategoriesByProductTypeId(int $productTypeId)
 * @method static void clearCaches()
 *
 * @see \CraftCms\Commerce\Shipping\ShippingCategories
 */
class ShippingCategories extends Facade
{
    #[Override]
    protected static function getFacadeAccessor(): string
    {
        return \CraftCms\Commerce\Shipping\ShippingCategories::class;
    }
}
