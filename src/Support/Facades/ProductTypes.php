<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Override;

/**
 * @method static \CraftCms\Commerce\Product\ProductType\Data\ProductType[] getViewableProductTypes()
 * @method static array getViewableProductTypeIds(bool $anySite = false)
 * @method static array getCreatableProductTypeIds()
 * @method static \CraftCms\Commerce\Product\ProductType\Data\ProductType[] getCreatableProductTypes()
 * @method static int[] getAllProductTypeIds()
 * @method static \CraftCms\Commerce\Product\ProductType\Data\ProductType[] getAllProductTypes()
 * @method static \CraftCms\Commerce\Product\ProductType\Data\ProductType|null getProductTypeByHandle(string $handle)
 * @method static \CraftCms\Commerce\Product\ProductType\Data\ProductType|null getProductTypeById(int $productTypeId)
 * @method static \CraftCms\Commerce\Product\ProductType\Data\ProductType|null getProductTypeByUid(string $uid)
 * @method static \CraftCms\Commerce\Product\ProductType\Data\ProductType[] getProductTypesByTaxCategoryId(int $taxCategoryId)
 * @method static \CraftCms\Commerce\Product\ProductType\Data\ProductType[] getProductTypesByShippingCategoryId(int $shippingCategoryId)
 * @method static \CraftCms\Commerce\Product\ProductType\Data\ProductTypeSite[] getProductTypeSites(int $productTypeId)
 * @method static bool saveProductType(\CraftCms\Commerce\Product\ProductType\Data\ProductType $productType, bool $runValidation = true)
 * @method static void handleChangedProductType(\CraftCms\Cms\ProjectConfig\Events\ConfigEvent $event)
 * @method static bool deleteProductTypeById(int $id)
 * @method static void handleDeletedProductType(\CraftCms\Cms\ProjectConfig\Events\ConfigEvent $event)
 * @method static void pruneDeletedSite(\CraftCms\Cms\Site\Events\SiteDeleted $event)
 * @method static bool isProductTypeTemplateValid(\CraftCms\Commerce\Product\ProductType\Data\ProductType $productType, int $siteId)
 * @method static void afterSaveSiteHandler(\CraftCms\Cms\Site\Events\SiteSaved $event)
 *
 * @see \CraftCms\Commerce\Product\ProductType\ProductTypes
 */
class ProductTypes extends Facade
{
    #[Override]
    protected static function getFacadeAccessor(): string
    {
        return \CraftCms\Commerce\Product\ProductType\ProductTypes::class;
    }
}
