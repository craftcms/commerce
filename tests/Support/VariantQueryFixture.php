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
use CraftCms\Commerce\Shipping\Data\ShippingCategory;
use CraftCms\Commerce\Shipping\ShippingCategories;
use CraftCms\Commerce\Store\Stores;
use CraftCms\Commerce\Tax\Data\TaxCategory;
use CraftCms\Commerce\Tax\TaxCategories;
use RuntimeException;

/**
 * Builds one product type ("tees") with two variants and a second ("hoodies") with one, plus a
 * dedicated shipping category and tax category assigned to a subset of the variants — for tests
 * that need to distinguish variants by price, shipping category, or tax category (mirrors the
 * legacy `tests-yii2/fixtures/data/products.php` "rad-hoodie"/"hypercolor-tshirt" split, minus
 * the multi-store/multi-site product it also seeded, which is out of scope here).
 */
class VariantQueryFixture
{
    public ProductType $teesType;

    public ProductType $hoodiesType;

    public ShippingCategory $specialShippingCategory;

    public TaxCategory $reducedTaxCategory;

    public Product $tee;

    public Variant $whiteVariant;

    public Variant $blueVariant;

    public Product $hoodie;

    public Variant $hoodieVariant;

    public static function seed(): self
    {
        $fixture = new self();
        $fixture->build();

        return $fixture;
    }

    private function build(): void
    {
        $site = Sites::getCurrentSite();
        $storeId = app(Stores::class)->getPrimaryStore()->id;

        $this->specialShippingCategory = $this->createShippingCategory($storeId);
        $this->reducedTaxCategory = $this->createTaxCategory();

        $this->teesType = $this->createProductType('tees', 'Tees', $site->id);
        $this->hoodiesType = $this->createProductType('hoodies', 'Hoodies', $site->id);

        // A variant's shipping category must be one its product type allows — see
        // Variant::beforeSave(), which silently resets it back to the store default otherwise.
        // The association is owned by the shipping category, not the product type.
        $this->specialShippingCategory->setProductTypes([$this->teesType, $this->hoodiesType]);
        if (!app(ShippingCategories::class)->saveShippingCategory($this->specialShippingCategory)) {
            throw new RuntimeException('Could not save shipping category: ' . json_encode($this->specialShippingCategory->errors()->all()));
        }

        $this->tee = $this->createProduct($this->teesType, 'Hypercolor T-Shirt', $site->id);

        $this->whiteVariant = $this->createVariant($this->tee, 'White', 'hct-white', 19.99, $site->id, isDefault: true, overrides: [
            'shippingCategoryId' => $this->specialShippingCategory->id,
            'taxCategoryId' => $this->reducedTaxCategory->id,
        ]);
        $this->blueVariant = $this->createVariant($this->tee, 'Blue', 'hct-blue', 21.99, $site->id);

        $this->hoodie = $this->createProduct($this->hoodiesType, 'Rad Hoodie', $site->id);
        $this->hoodieVariant = $this->createVariant($this->hoodie, 'Rad Hoodie', 'rad-hood', 123.99, $site->id, isDefault: true, overrides: [
            'shippingCategoryId' => $this->specialShippingCategory->id,
        ]);
    }

    private function createShippingCategory(int $storeId): ShippingCategory
    {
        $category = new ShippingCategory();
        $category->storeId = $storeId;
        $category->name = 'Special Shipping';
        $category->handle = 'specialShipping';

        if (!app(ShippingCategories::class)->saveShippingCategory($category)) {
            throw new RuntimeException('Could not save shipping category: ' . json_encode($category->errors()->all()));
        }

        return $category;
    }

    private function createTaxCategory(): TaxCategory
    {
        $category = new TaxCategory();
        $category->name = 'Reduced Tax';
        $category->handle = 'reducedTax';

        if (!app(TaxCategories::class)->saveTaxCategory($category)) {
            throw new RuntimeException('Could not save tax category: ' . json_encode($category->errors()->all()));
        }

        return $category;
    }

    private function createProductType(string $handle, string $name, int $siteId): ProductType
    {
        $productType = new ProductType();
        $productType->name = $name;
        $productType->handle = $handle;
        $productType->hasVariantTitleField = true;
        $productType->variantTitleFormat = '{product.title} - {title}';

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

        return Product::find()->id($product->id)->one();
    }

    /** @param array<string, mixed> $overrides */
    private function createVariant(Product $product, string $title, string $sku, float $price, int $siteId, bool $isDefault = false, array $overrides = []): Variant
    {
        $variant = new Variant();
        $variant->title = $title;
        $variant->setPrimaryOwner($product);
        $variant->setSku($sku);
        $variant->setBasePrice($price);
        $variant->isDefault = $isDefault;
        $variant->promotable = true;
        $variant->siteId = $siteId;

        if (isset($overrides['shippingCategoryId'])) {
            $variant->setShippingCategoryId($overrides['shippingCategoryId']);
        }
        if (isset($overrides['taxCategoryId'])) {
            $variant->setTaxCategoryId($overrides['taxCategoryId']);
        }

        if (!Elements::saveElement($variant)) {
            throw new RuntimeException('Could not save variant: ' . json_encode($variant->errors()->all()));
        }

        return Variant::find()->id($variant->id)->one();
    }
}
