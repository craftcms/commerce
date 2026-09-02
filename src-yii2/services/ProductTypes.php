<?php

namespace craft\commerce\services;

use craft\events\ConfigEvent;
use craft\events\DeleteSiteEvent;
use CraftCms\Commerce\Product\ProductType\Data\ProductTypeSite;
use CraftCms\Commerce\Product\ProductType\Data\ProductType;
use yii\base\Component;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\Product\ProductType\ProductTypes::class)` instead.
 */
class ProductTypes extends Component
{
    public const string EVENT_BEFORE_SAVE_PRODUCTTYPE = \CraftCms\Commerce\Product\ProductType\ProductTypes::EVENT_BEFORE_SAVE_PRODUCTTYPE;

    public const string EVENT_AFTER_SAVE_PRODUCTTYPE = \CraftCms\Commerce\Product\ProductType\ProductTypes::EVENT_AFTER_SAVE_PRODUCTTYPE;

    public const string CONFIG_PRODUCTTYPES_KEY = \CraftCms\Commerce\Product\ProductType\ProductTypes::CONFIG_PRODUCTTYPES_KEY;

    /**
     * @return ProductType[]
     */
    public function getViewableProductTypes(): array
    {
        return app(\CraftCms\Commerce\Product\ProductType\ProductTypes::class)->getViewableProductTypes();
    }

    public function getViewableProductTypeIds(bool $anySite = false): array
    {
        return app(\CraftCms\Commerce\Product\ProductType\ProductTypes::class)->getViewableProductTypeIds($anySite);
    }

    public function getCreatableProductTypeIds(): array
    {
        return app(\CraftCms\Commerce\Product\ProductType\ProductTypes::class)->getCreatableProductTypeIds();
    }

    /**
     * @return ProductType[]
     */
    public function getCreatableProductTypes(): array
    {
        return app(\CraftCms\Commerce\Product\ProductType\ProductTypes::class)->getCreatableProductTypes();
    }

    public function getAllProductTypeIds(): array
    {
        return app(\CraftCms\Commerce\Product\ProductType\ProductTypes::class)->getAllProductTypeIds();
    }

    /**
     * @return ProductType[]
     */
    public function getAllProductTypes(): array
    {
        return app(\CraftCms\Commerce\Product\ProductType\ProductTypes::class)->getAllProductTypes();
    }

    public function getProductTypeByHandle(string $handle): ?ProductType
    {
        return app(\CraftCms\Commerce\Product\ProductType\ProductTypes::class)->getProductTypeByHandle($handle);
    }

    public function getProductTypeById(int $productTypeId): ?ProductType
    {
        return app(\CraftCms\Commerce\Product\ProductType\ProductTypes::class)->getProductTypeById($productTypeId);
    }

    public function getProductTypeByUid(string $uid): ?ProductType
    {
        return app(\CraftCms\Commerce\Product\ProductType\ProductTypes::class)->getProductTypeByUid($uid);
    }

    /**
     * @return ProductType[]
     */
    public function getProductTypesByTaxCategoryId(int $taxCategoryId): array
    {
        return app(\CraftCms\Commerce\Product\ProductType\ProductTypes::class)->getProductTypesByTaxCategoryId($taxCategoryId);
    }

    /**
     * @return ProductType[]
     */
    public function getProductTypesByShippingCategoryId(int $shippingCategoryId): array
    {
        return app(\CraftCms\Commerce\Product\ProductType\ProductTypes::class)->getProductTypesByShippingCategoryId($shippingCategoryId);
    }

    /**
     * @return ProductTypeSite[]
     */
    public function getProductTypeSites(int $productTypeId): array
    {
        return app(\CraftCms\Commerce\Product\ProductType\ProductTypes::class)->getProductTypeSites($productTypeId);
    }

    public function saveProductType(ProductType $productType, bool $runValidation = true): bool
    {
        return app(\CraftCms\Commerce\Product\ProductType\ProductTypes::class)->saveProductType($productType, $runValidation);
    }

    public function handleChangedProductType(ConfigEvent $event): void
    {
        app(\CraftCms\Commerce\Product\ProductType\ProductTypes::class)->handleChangedProductType(new \CraftCms\Cms\ProjectConfig\Events\ItemUpdated($event->path, $event->oldValue, $event->newValue, $event->tokenMatches));
    }

    public function deleteProductTypeById(int $id): bool
    {
        return app(\CraftCms\Commerce\Product\ProductType\ProductTypes::class)->deleteProductTypeById($id);
    }

    public function handleDeletedProductType(ConfigEvent $event): void
    {
        app(\CraftCms\Commerce\Product\ProductType\ProductTypes::class)->handleDeletedProductType(new \CraftCms\Cms\ProjectConfig\Events\ItemRemoved($event->path, $event->oldValue, $event->newValue, $event->tokenMatches));
    }

    public function pruneDeletedSite(DeleteSiteEvent $event): void
    {
        app(\CraftCms\Commerce\Product\ProductType\ProductTypes::class)->pruneDeletedSite(new \CraftCms\Cms\Site\Events\SiteDeleted(site: $event->site));
    }

    public function isProductTypeTemplateValid(ProductType $productType, int $siteId): bool
    {
        return app(\CraftCms\Commerce\Product\ProductType\ProductTypes::class)->isProductTypeTemplateValid($productType, $siteId);
    }
}
