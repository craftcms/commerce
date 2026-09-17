<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Tests\Support;

use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\User\Data\UserGroup;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Cms\User\UserGroups;
use CraftCms\Cms\User\Users;
use CraftCms\Commerce\Product\Elements\Product;
use CraftCms\Commerce\Product\ProductType\Data\ProductType;
use CraftCms\Commerce\Product\ProductType\Data\ProductTypeSite;
use CraftCms\Commerce\Product\ProductType\ProductTypes;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use CraftCms\Commerce\Promotion\Data\Sale;
use CraftCms\Commerce\Promotion\Models\Sale as SaleRecord;
use CraftCms\Commerce\Promotion\Sales;
use RuntimeException;

/**
 * Builds a two-sale scenario: a "percentage" sale scoped to a single purchasable, and an
 * "all relationships" sale scoped to a purchasable and a user group.
 *
 * Neither sale is scoped to categories — category-based sale scoping is currently broken (see
 * COM-644/645/646/647) so it isn't exercised here.
 */
class SalesFixture
{
    public Product $hoodie;

    public Variant $radHood;

    public Product $tShirt;

    public Variant $hctWhite;

    public UserGroup $group;

    public User $customer;

    public Sale $percentageSale;

    public Sale $allRelationshipsSale;

    public static function seed(): self
    {
        $fixture = new self();
        $fixture->build();

        return $fixture;
    }

    private function build(): void
    {
        $site = Sites::getCurrentSite();

        $hoodiesType = $this->createProductType('hoodies', 'Hoodies', $site->id);
        [$this->hoodie, $this->radHood] = $this->createProductAndVariant($hoodiesType, 'Rad Hoodie', 'rad-hood', 123.99, $site->id);

        $tShirtsType = $this->createProductType('tShirts', 'T-Shirts', $site->id);
        [$this->tShirt, $this->hctWhite] = $this->createProductAndVariant($tShirtsType, 'Hypercolor T-Shirt', 'hct-white', 19.99, $site->id);

        $this->group = $this->createUserGroup('Sales Test Group', 'salesTestGroup');
        $this->customer = $this->createCustomer($this->group);

        $this->percentageSale = $this->createSale([
            'name' => 'My Percentage Sale',
            'description' => 'My test percentage sale.',
            'sortOrder' => 1,
            'apply' => SaleRecord::APPLY_BY_PERCENT,
            'applyAmount' => -0.1000,
            'allGroups' => true,
            'allPurchasables' => false,
            'allCategories' => true,
        ], purchasableIds: [$this->radHood->id]);

        $this->allRelationshipsSale = $this->createSale([
            'name' => 'All Relationships',
            'description' => 'All the relationships.',
            'sortOrder' => 2,
            'apply' => SaleRecord::APPLY_BY_PERCENT,
            'applyAmount' => -0.2000,
            'allGroups' => false,
            'allPurchasables' => false,
            'allCategories' => true,
        ], purchasableIds: [$this->hctWhite->id], userGroupIds: [$this->group->id]);
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

    private function createUserGroup(string $name, string $handle): UserGroup
    {
        $group = new UserGroup();
        $group->name = $name;
        $group->handle = $handle;

        if (!app(UserGroups::class)->saveGroup($group)) {
            throw new RuntimeException('Could not save user group: ' . $handle);
        }

        return $group;
    }

    private function createCustomer(UserGroup $group): User
    {
        $customer = new User();
        $customer->username = 'salesTestCustomer';
        $customer->email = 'salesTestCustomer@crafttest.com';
        $customer->active = true;
        if (!Elements::saveElement($customer)) {
            throw new RuntimeException('Could not save customer: ' . json_encode($customer->errors()->all()));
        }

        if (!app(Users::class)->assignUserToGroups($customer->id, [$group->id])) {
            throw new RuntimeException('Could not assign customer to group: ' . $group->handle);
        }

        // Re-query fresh so `getGroups()` isn't memoized empty from before the assignment above.
        return User::find()->id($customer->id)->one();
    }

    /**
     * @param array<string, mixed> $attributes
     * @param int[] $purchasableIds
     * @param int[] $userGroupIds
     */
    private function createSale(array $attributes, array $purchasableIds = [], array $userGroupIds = []): Sale
    {
        $sale = new Sale($attributes);
        $sale->setPurchasableIds($purchasableIds);
        $sale->setUserGroupIds($userGroupIds);

        if (!app(Sales::class)->saveSale($sale)) {
            throw new RuntimeException('Could not save sale: ' . json_encode($sale->errors()->all()));
        }

        return $sale;
    }
}
