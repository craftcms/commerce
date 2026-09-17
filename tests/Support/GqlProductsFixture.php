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
 * Mirrors the legacy `tests-yii2/fixtures/data/product-types.php` and `products.php` GQL fixture
 * data (handles, slugs, and skus are asserted against literally by the ported GQL tests), minus
 * the UK-store-only product type/product those files also seeded, which is out of scope here.
 */
class GqlProductsFixture
{
    public ProductType $hoodiesType;

    public ProductType $tShirtsType;

    public Product $hoodie;

    public Variant $hoodieVariant;

    public Product $tShirt;

    public Variant $whiteVariant;

    public Variant $blueVariant;

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

        $this->hoodie = $this->createProduct($this->hoodiesType, 'Rad Hoodie', 'rad-hoodie', $site->id);
        $this->hoodieVariant = $this->createVariant($this->hoodie, 'Rad Hoodie', 'rad-hood', 123.99, $site->id, isDefault: true);

        $this->tShirt = $this->createProduct($this->tShirtsType, 'Hypercolor T-Shirt', 'hypercolor-tshirt', $site->id);
        $this->whiteVariant = $this->createVariant($this->tShirt, 'White', 'hct-white', 19.99, $site->id, isDefault: true);
        $this->blueVariant = $this->createVariant($this->tShirt, 'Blue', 'hct-blue', 21.99, $site->id);
    }

    private function createProductType(string $handle, string $name, int $siteId): ProductType
    {
        $productType = new ProductType();
        $productType->name = $name;
        $productType->handle = $handle;
        // Matches the legacy fixture: with no dedicated title field, a variant's title always
        // derives from the default '{product.title}' format, regardless of the title we set on
        // it when creating it below.
        $productType->hasVariantTitleField = false;

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

    private function createProduct(ProductType $productType, string $title, string $slug, int $siteId): Product
    {
        $product = new Product();
        $product->typeId = $productType->id;
        $product->title = $title;
        $product->slug = $slug;
        $product->enabled = true;
        $product->siteId = $siteId;
        if (!Elements::saveElement($product)) {
            throw new RuntimeException('Could not save product: ' . json_encode($product->errors()->all()));
        }

        return Product::find()->id($product->id)->one();
    }

    private function createVariant(Product $product, string $title, string $sku, float $price, int $siteId, bool $isDefault = false): Variant
    {
        $variant = new Variant();
        $variant->title = $title;
        $variant->setPrimaryOwner($product);
        $variant->setSku($sku);
        $variant->setBasePrice($price);
        $variant->isDefault = $isDefault;
        $variant->promotable = true;
        $variant->siteId = $siteId;

        if (!Elements::saveElement($variant)) {
            throw new RuntimeException('Could not save variant: ' . json_encode($variant->errors()->all()));
        }

        return Variant::find()->id($variant->id)->one();
    }
}
