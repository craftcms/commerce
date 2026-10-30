<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Tests\Support;

use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Commerce\Product\Elements\Product;
use CraftCms\Commerce\Product\ProductType\Data\ProductType;
use CraftCms\Commerce\Product\ProductType\Data\ProductTypeSite;
use CraftCms\Commerce\Product\ProductType\ProductTypes;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use RuntimeException;

/**
 * Builds a three-store layout (via {@see StoresFixture}) with two product types available in
 * every store ("hoodies", "tShirts") and one available only in the UK store ("ukOnly"), for
 * catalog pricing generation tests that need purchasables spread across multiple stores.
 */
class CatalogPricingFixture
{
    public StoresFixture $stores;

    public ProductType $hoodiesType;

    public ProductType $tShirtsType;

    public ProductType $ukOnlyType;

    public Product $hoodie;

    public Variant $radHood;

    public Product $tShirt;

    public Variant $hctWhite;

    public Variant $hctBlue;

    public Product $bus;

    public Variant $ddbRed;

    public static function seed(): self
    {
        $fixture = new self();
        $fixture->build();

        return $fixture;
    }

    private function build(): void
    {
        $this->stores = StoresFixture::seed();

        $allSiteIds = [$this->stores->usSite->id, $this->stores->euSite->id, $this->stores->ukSite->id];

        $this->hoodiesType = $this->createProductType('hoodies', 'Hoodies', $allSiteIds);
        $this->tShirtsType = $this->createProductType('tShirts', 'T-Shirts', $allSiteIds);
        $this->ukOnlyType = $this->createProductType('ukOnly', 'UK Only Product Type', [$this->stores->ukSite->id]);

        [$this->hoodie, $this->radHood] = $this->createProductAndVariant(
            $this->hoodiesType,
            'Rad Hoodie',
            'rad-hood',
            123.99,
            $this->stores->usSite->id,
        );

        $this->tShirt = $this->createProduct($this->tShirtsType, 'Hypercolor T-Shirt', $this->stores->usSite->id);
        $this->hctWhite = $this->createVariant($this->tShirt, 'White', 'hct-white', 19.99, isDefault: true);
        $this->hctBlue = $this->createVariant($this->tShirt, 'Blue', 'hct-blue', 21.99, isDefault: false);

        [$this->bus, $this->ddbRed] = $this->createProductAndVariant(
            $this->ukOnlyType,
            'Double Decker Bus Toy',
            'ddb-red',
            24.99,
            $this->stores->ukSite->id,
        );
    }

    /** @param int[] $siteIds */
    private function createProductType(string $handle, string $name, array $siteIds): ProductType
    {
        $productType = new ProductType();
        $productType->name = $name;
        $productType->handle = $handle;
        $productType->hasVariantTitleField = false;
        $productType->variantTitleFormat = '{product.title}';

        $siteSettings = [];
        foreach ($siteIds as $siteId) {
            $settings = new ProductTypeSite();
            $settings->siteId = $siteId;
            $settings->hasUrls = false;
            $settings->enabledByDefault = true;
            $siteSettings[$siteId] = $settings;
        }
        $productType->setSiteSettings($siteSettings);

        if (!app(ProductTypes::class)->saveProductType($productType)) {
            throw new RuntimeException('Could not save product type: ' . json_encode($productType->errors()->all()));
        }

        return $productType;
    }

    /** @return array{0: Product, 1: Variant} */
    private function createProductAndVariant(ProductType $productType, string $title, string $sku, float $price, int $siteId): array
    {
        $product = $this->createProduct($productType, $title, $siteId);
        $variant = $this->createVariant($product, $title, $sku, $price, isDefault: true);

        return [$product, $variant];
    }

    private function createProduct(ProductType $productType, string $title, int $siteId): Product
    {
        $product = new Product();
        $product->typeId = $productType->id;
        $product->title = $title;
        $product->enabled = true;
        $product->siteId = $siteId;
        if (!Elements::saveElement($product)) {
            throw new RuntimeException('Could not save product: ' . json_encode($product->errors()->all()));
        }

        return Product::find()->id($product->id)->siteId($siteId)->one();
    }

    private function createVariant(Product $product, string $title, string $sku, float $price, bool $isDefault): Variant
    {
        $variant = new Variant();
        $variant->title = $title;
        $variant->setPrimaryOwner($product);
        $variant->setSku($sku);
        $variant->setBasePrice($price);
        $variant->isDefault = $isDefault;
        $variant->promotable = true;
        $variant->siteId = $product->siteId;
        if (!Elements::saveElement($variant)) {
            throw new RuntimeException('Could not save variant: ' . json_encode($variant->errors()->all()));
        }

        return Variant::find()->id($variant->id)->siteId($product->siteId)->one();
    }
}
