<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Tests\Support;

use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Commerce\Product\Elements\Product;
use CraftCms\Commerce\Product\ProductType\Data\ProductType;
use CraftCms\Commerce\Product\ProductType\Data\ProductTypeSite;
use CraftCms\Commerce\Product\ProductType\ProductTypes;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use RuntimeException;

/**
 * Creates product types, products and variants that issue licenses.
 */
class LicensesFixture
{
    public static function productType(string $handle = 'software', bool $hasLicenses = true): ProductType
    {
        $siteId = Sites::getPrimarySite()->id;

        $productType = new ProductType();
        $productType->name = ucfirst($handle);
        $productType->handle = $handle;
        $productType->hasLicenses = $hasLicenses;

        $siteSettings = new ProductTypeSite();
        $siteSettings->siteId = $siteId;
        $siteSettings->hasUrls = false;
        $productType->setSiteSettings([$siteId => $siteSettings]);

        if (!app(ProductTypes::class)->saveProductType($productType)) {
            throw new RuntimeException('Could not save product type: ' . json_encode($productType->errors()->all()));
        }

        return app(ProductTypes::class)->getProductTypeByHandle($handle);
    }

    public static function product(ProductType $productType, string $title = 'Editor'): Product
    {
        $product = new Product();
        $product->typeId = $productType->id;
        $product->title = $title;
        $product->siteId = Sites::getPrimarySite()->id;

        if (!Elements::saveElement($product)) {
            throw new RuntimeException('Could not save product: ' . json_encode($product->errors()->all()));
        }

        return Product::find()->id($product->id)->one();
    }

    public static function variant(Product $product, string $sku, bool $issuesLicense = true, float $price = 20): Variant
    {
        $variant = new Variant();
        $variant->title = $sku;
        $variant->setPrimaryOwner($product);
        $variant->setSku($sku);
        $variant->setBasePrice($price);
        $variant->issuesLicense = $issuesLicense;
        $variant->siteId = Sites::getPrimarySite()->id;

        if (!Elements::saveElement($variant)) {
            throw new RuntimeException('Could not save variant: ' . json_encode($variant->errors()->all()));
        }

        return Variant::find()->id($variant->id)->one();
    }
}
