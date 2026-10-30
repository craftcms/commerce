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
 * Builds two product types ("hoodies"/"tShirts"), each with one product, for tests that need
 * to distinguish products by type — e.g. `ProductTypeConditionRule`.
 */
class ProductConditionsFixture
{
    public ProductType $hoodiesType;

    public ProductType $tShirtsType;

    public Product $hoodie;

    public Variant $hoodieVariant;

    public Product $tShirt;

    public Variant $tShirtVariant;

    public static function seed(): self
    {
        $fixture = new self();
        $fixture->build();

        return $fixture;
    }

    private function build(): void
    {
        $site = Sites::getCurrentSite();

        $this->hoodiesType = $this->createProductType('hoodies', 'Hoodies', $site->id);
        $this->tShirtsType = $this->createProductType('tShirts', 'T-Shirts', $site->id);

        [$this->hoodie, $this->hoodieVariant] = $this->createProduct(
            $this->hoodiesType,
            'Rad Hoodie',
            'rad-hood',
            123.99,
            $site->id,
        );

        [$this->tShirt, $this->tShirtVariant] = $this->createProduct(
            $this->tShirtsType,
            'Plain T-Shirt',
            'plain-tee',
            19.99,
            $site->id,
        );
    }

    private function createProductType(string $handle, string $name, int $siteId): ProductType
    {
        $productType = new ProductType();
        $productType->name = $name;
        $productType->handle = $handle;
        $productType->hasVariantTitleField = false;
        $productType->variantTitleFormat = '{product.title}';

        $siteSettings = new ProductTypeSite();
        $siteSettings->siteId = $siteId;
        $siteSettings->hasUrls = false;
        $siteSettings->enabledByDefault = true;
        $productType->setSiteSettings([$siteId => $siteSettings]);

        if (!app(ProductTypes::class)->saveProductType($productType)) {
            throw new RuntimeException('Could not save product type: ' . json_encode($productType->errors()->all()));
        }

        return $productType;
    }

    /** @return array{0: Product, 1: Variant} */
    private function createProduct(ProductType $productType, string $title, string $sku, float $price, int $siteId): array
    {
        $product = new Product();
        $product->typeId = $productType->id;
        $product->title = $title;
        $product->enabled = true;
        $product->siteId = $siteId;
        if (!Elements::saveElement($product)) {
            throw new RuntimeException('Could not save product: ' . json_encode($product->errors()->all()));
        }

        $variant = new Variant();
        $variant->title = $title;
        $variant->setPrimaryOwner($product);
        $variant->setSku($sku);
        $variant->setBasePrice($price);
        $variant->isDefault = true;
        $variant->promotable = true;
        $variant->siteId = $siteId;
        if (!Elements::saveElement($variant)) {
            throw new RuntimeException('Could not save variant: ' . json_encode($variant->errors()->all()));
        }

        return [Product::find()->id($product->id)->one(), Variant::find()->id($variant->id)->one()];
    }
}
