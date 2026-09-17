<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Tests\Support;

use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Product\Elements\Product;
use CraftCms\Commerce\Product\ProductType\Data\ProductType;
use CraftCms\Commerce\Product\ProductType\Data\ProductTypeSite;
use CraftCms\Commerce\Product\ProductType\ProductTypes;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use CraftCms\Commerce\Promotion\Data\Coupon;
use CraftCms\Commerce\Promotion\Data\Discount;
use CraftCms\Commerce\Promotion\Discounts;
use CraftCms\Commerce\Store\Stores;
use RuntimeException;

/**
 * Builds a "discount_with_coupon" scenario (a discount requiring the `discount_1` coupon code),
 * plus three purchasables (`rad-hood`, `hct-white`, `hct-blue`) and a signed-in customer, for the
 * `Discounts`/`Coupons` service tests.
 *
 * Like `SalesFixture`, this deliberately never sets `allCategories: false` with real category
 * IDs — category-based discount matching is currently broken (see COM-644/645/646/647) so it
 * isn't exercised here.
 */
class DiscountsFixture
{
    public Product $hoodie;

    public Variant $radHood;

    public Product $tShirt;

    public Variant $hctWhite;

    public Variant $hctBlue;

    public User $customer;

    public Discount $discountWithCoupon;

    public int $storeId;

    public static function seed(): self
    {
        $fixture = new self();
        $fixture->build();

        return $fixture;
    }

    private function build(): void
    {
        $site = Sites::getCurrentSite();
        $this->storeId = app(Stores::class)->getPrimaryStore()->id;

        $hoodiesType = $this->createProductType('discountsHoodies', 'Hoodies', $site->id);
        [$this->hoodie, $this->radHood] = $this->createProductAndVariant($hoodiesType, 'Rad Hoodie', 'rad-hood', 123.99, $site->id);

        $tShirtsType = $this->createProductType('discountsTShirts', 'T-Shirts', $site->id);
        $this->tShirt = $this->createProduct($tShirtsType, 'Hypercolor T-Shirt', $site->id);
        $this->hctWhite = $this->createVariant($this->tShirt, 'White', 'hct-white', 19.99, $site->id, isDefault: true);
        $this->hctBlue = $this->createVariant($this->tShirt, 'Blue', 'hct-blue', 21.99, $site->id, isDefault: false);

        $this->customer = $this->createCustomer();

        $this->discountWithCoupon = $this->createDiscount([
            'name' => 'Discount 1',
            'storeId' => $this->storeId,
            'perUserLimit' => 1,
            'totalDiscountUseLimit' => 2,
            'baseDiscount' => 10,
            'perItemDiscount' => 5,
            'percentDiscount' => 15.25,
            'enabled' => true,
            'allCategories' => true,
            'allPurchasables' => true,
            'percentageOffSubject' => 'original',
            'requireCouponCode' => true,
        ], coupons: [new Coupon(['code' => 'discount_1', 'uses' => 0, 'maxUses' => null])]);
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
    private function createProductAndVariant(ProductType $productType, string $title, string $sku, float $price, int $siteId): array
    {
        $product = $this->createProduct($productType, $title, $siteId);
        $variant = $this->createVariant($product, $title, $sku, $price, $siteId, isDefault: true);

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

        return Product::find()->id($product->id)->one();
    }

    private function createVariant(Product $product, string $title, string $sku, float $price, int $siteId, bool $isDefault): Variant
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

    private function createCustomer(): User
    {
        $customer = new User();
        $customer->username = 'discountsTestCustomer';
        $customer->email = 'discountsTestCustomer@crafttest.com';
        $customer->active = true;
        if (!Elements::saveElement($customer)) {
            throw new RuntimeException('Could not save customer: ' . json_encode($customer->errors()->all()));
        }

        return User::find()->id($customer->id)->one();
    }

    /**
     * @param array<string, mixed> $attributes
     * @param int[] $purchasableIds
     * @param int[] $categoryIds
     * @param Coupon[] $coupons
     */
    private function createDiscount(array $attributes, array $purchasableIds = [], array $categoryIds = [], array $coupons = []): Discount
    {
        $discount = new Discount($attributes);
        $discount->setPurchasableIds($purchasableIds);
        $discount->setCategoryIds($categoryIds);

        if (!empty($coupons)) {
            $discount->setCoupons($coupons);
        }

        if (!app(Discounts::class)->saveDiscount($discount)) {
            throw new RuntimeException('Could not save discount: ' . json_encode($discount->errors()->all()));
        }

        return $discount;
    }
}
