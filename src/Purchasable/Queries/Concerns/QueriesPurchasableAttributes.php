<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Purchasable\Queries\Concerns;

use CraftCms\Commerce\Purchasable\Queries\PurchasableQuery;
use CraftCms\Commerce\Shipping\Data\ShippingCategory;
use CraftCms\Commerce\Tax\Data\TaxCategory;
use Illuminate\Contracts\Database\Query\Builder as BuilderContract;
use Illuminate\Support\Facades\DB;

/**
 * Purchasable-specific scope properties, mirroring the `QueriesFields`/`QueriesAssetLocation`
 * convention from craft core: each scope has a public property, a fluent setter, and a public
 * static `apply*()` method that writes directly to a `Builder`. Condition rules should always call
 * the static method directly (see `CraftCms\Commerce\Purchasable\Conditions\SkuConditionRule` and
 * `CraftCms\Commerce\Product\Conditions\ProductVariant*ConditionRule`), never the fluent setter —
 * see `CraftCms\Commerce\Order\Queries\Concerns\QueriesOrderAttributes` for why.
 *
 * @internal
 */
trait QueriesPurchasableAttributes
{
    public ?bool $availableForPurchase = null;

    public mixed $sku = null;

    public mixed $price = null;

    public mixed $promotionalPrice = null;

    public ?bool $onPromotion = null;

    public mixed $salePrice = null;

    public mixed $width = false;

    public mixed $height = false;

    public mixed $length = false;

    public mixed $weight = false;

    public mixed $stock = null;

    public ?bool $hasStock = null;

    public mixed $shippingCategoryId = null;

    public mixed $taxCategoryId = null;

    public ?bool $inventoryTracked = null;

    protected function initQueriesPurchasableAttributes(): void
    {
        $this->beforeQuery(function(PurchasableQuery $query) {
            // $query->hasCatalogPricingRules is captured once, at construction time, and must stay
            // in sync with the decision the constructor already made about which columns/joins
            // (catalogprices.* vs purchasables_stores.*) are actually available on this query —
            // recomputing it here could disagree with that decision and reference a nonexistent alias.
            static::applyPrice($query, $query->price, $query->hasCatalogPricingRules);
            static::applyPromotionalPrice($query, $query->promotionalPrice, $query->hasCatalogPricingRules);
            static::applyOnPromotion($query, $query->onPromotion, $query->hasCatalogPricingRules);
            static::applySalePrice($query, $query->salePrice, $query->hasCatalogPricingRules);
            static::applySku($query, $query->sku);
            static::applyStock($query, $query->stock);
            static::applyInventoryTracked($query, $query->inventoryTracked);
            static::applyAvailableForPurchase($query, $query->availableForPurchase);
            static::applyShippingCategoryId($query, $query->shippingCategoryId);
            static::applyTaxCategoryId($query, $query->taxCategoryId);
            static::applyWidth($query, $query->width);
            static::applyHeight($query, $query->height);
            static::applyLength($query, $query->length);
            static::applyWeight($query, $query->weight);
            static::applyHasStock($query, $query->hasStock);
        });
    }

    public static function applyPrice(BuilderContract $query, mixed $value, bool $hasCatalogPricingRules): void
    {
        if (!isset($value)) {
            return;
        }

        $query->whereParam($hasCatalogPricingRules ? 'catalogprices.price' : 'purchasables_stores.basePrice', $value);
    }

    public static function applyPromotionalPrice(BuilderContract $query, mixed $value, bool $hasCatalogPricingRules): void
    {
        if (!isset($value)) {
            return;
        }

        $query->whereParam($hasCatalogPricingRules ? 'catalogprices.promotionalPrice' : 'purchasables_stores.basePromotionalPrice', $value);
    }

    public static function applyOnPromotion(BuilderContract $query, ?bool $value, bool $hasCatalogPricingRules): void
    {
        if (!isset($value)) {
            return;
        }

        [$promotionalPriceColumn, $priceColumn] = $hasCatalogPricingRules
            ? ['catalogprices.promotionalPrice', 'catalogprices.price']
            : ['purchasables_stores.basePromotionalPrice', 'purchasables_stores.basePrice'];

        if ($value) {
            $query->whereColumn($promotionalPriceColumn, '<', $priceColumn);
        } else {
            $query->whereColumn($priceColumn, $hasCatalogPricingRules ? '=' : '<', $promotionalPriceColumn);
        }
    }

    public static function applySalePrice(BuilderContract $query, mixed $value, bool $hasCatalogPricingRules): void
    {
        if (!isset($value)) {
            return;
        }

        if ($hasCatalogPricingRules) {
            $query->whereParam('catalogprices.salePrice', $value);
            return;
        }

        $query->whereParam(
            DB::raw('CASE WHEN purchasables_stores.basePromotionalPrice < purchasables_stores.basePrice THEN purchasables_stores.basePromotionalPrice ELSE purchasables_stores.basePrice END'),
            $value,
        );
    }

    public static function applySku(BuilderContract $query, mixed $value): void
    {
        if (!isset($value)) {
            return;
        }

        $query->whereParam('commerce_purchasables.sku', $value);
    }

    /**
     * We don't join the inventory levels table, and rely on the cached store available total.
     */
    public static function applyStock(BuilderContract $query, mixed $value): void
    {
        if (!isset($value)) {
            return;
        }

        $query->whereParam('purchasables_stores.stock', $value);
    }

    public static function applyInventoryTracked(BuilderContract $query, ?bool $value): void
    {
        if (!isset($value)) {
            return;
        }

        $query->whereParam('purchasables_stores.inventoryTracked', $value);
    }

    public static function applyAvailableForPurchase(BuilderContract $query, ?bool $value): void
    {
        if (!isset($value)) {
            return;
        }

        $query->where('purchasables_stores.availableForPurchase', $value);
    }

    public static function applyShippingCategoryId(BuilderContract $query, mixed $value): void
    {
        if (!isset($value)) {
            return;
        }

        $query->whereParam('purchasables_stores.shippingCategoryId', $value);
    }

    public static function applyTaxCategoryId(BuilderContract $query, mixed $value): void
    {
        if (!isset($value)) {
            return;
        }

        $query->whereParam('commerce_purchasables.taxCategoryId', $value);
    }

    public static function applyWidth(BuilderContract $query, mixed $value): void
    {
        if ($value === false) {
            return;
        }

        if ($value === null) {
            $query->whereNull('commerce_purchasables.width');
        } else {
            $query->whereParam('commerce_purchasables.width', $value);
        }
    }

    public static function applyHeight(BuilderContract $query, mixed $value): void
    {
        if ($value === false) {
            return;
        }

        if ($value === null) {
            $query->whereNull('commerce_purchasables.height');
        } else {
            $query->whereParam('commerce_purchasables.height', $value);
        }
    }

    public static function applyLength(BuilderContract $query, mixed $value): void
    {
        if ($value === false) {
            return;
        }

        if ($value === null) {
            $query->whereNull('commerce_purchasables.length');
        } else {
            $query->whereParam('commerce_purchasables.length', $value);
        }
    }

    public static function applyWeight(BuilderContract $query, mixed $value): void
    {
        if ($value === false) {
            return;
        }

        if ($value === null) {
            $query->whereNull('commerce_purchasables.weight');
        } else {
            $query->whereParam('commerce_purchasables.weight', $value);
        }
    }

    public static function applyHasStock(BuilderContract $query, ?bool $value): void
    {
        if (!isset($value)) {
            return;
        }

        if ($value) {
            $query->where(function($q) {
                $q->where('purchasables_stores.inventoryTracked', false)
                    ->orWhere(function($q2) {
                        $q2->where('purchasables_stores.inventoryTracked', true)
                            ->where('purchasables_stores.stock', '>', 0);
                    });
            });
        } else {
            $query->where('purchasables_stores.inventoryTracked', true)
                ->where('purchasables_stores.stock', '<', 1);
        }
    }

    public function availableForPurchase(?bool $value = true): static
    {
        $this->availableForPurchase = $value;
        return $this;
    }

    public function sku(mixed $value): static
    {
        $this->sku = $value;
        return $this;
    }

    public function stock(mixed $value): static
    {
        $this->stock = $value;
        return $this;
    }

    public function hasStock(?bool $value = true): static
    {
        $this->hasStock = $value;
        return $this;
    }

    public function width(mixed $value): static
    {
        $this->width = $value;
        return $this;
    }

    public function height(mixed $value): static
    {
        $this->height = $value;
        return $this;
    }

    public function length(mixed $value): static
    {
        $this->length = $value;
        return $this;
    }

    public function weight(mixed $value): static
    {
        $this->weight = $value;
        return $this;
    }

    public function price(mixed $value): static
    {
        $this->price = $value;
        return $this;
    }

    public function inventoryTracked(?bool $value = true): static
    {
        $this->inventoryTracked = $value;
        return $this;
    }

    public function promotionalPrice(mixed $value): static
    {
        $this->promotionalPrice = $value;
        return $this;
    }

    public function salePrice(mixed $value): static
    {
        $this->salePrice = $value;
        return $this;
    }

    public function shippingCategoryId(mixed $value): static
    {
        $this->shippingCategoryId = $value;
        return $this;
    }

    public function shippingCategory(mixed $value): static
    {
        if ($value instanceof ShippingCategory) {
            $this->shippingCategoryId = [$value->id];
        } elseif ($value !== null) {
            $this->shippingCategoryId = DB::table(\CraftCms\Commerce\Database\Table::SHIPPINGCATEGORIES . ' as shippingcategories')
                ->whereColumn('shippingcategories.id', 'purchasables_stores.shippingCategoryId')
                ->whereParam('handle', $value)
                ->select('shippingcategories.id');
        } else {
            $this->shippingCategoryId = null;
        }

        return $this;
    }

    public function taxCategoryId(mixed $value): static
    {
        $this->taxCategoryId = $value;
        return $this;
    }

    public function taxCategory(mixed $value): static
    {
        if ($value instanceof TaxCategory) {
            $this->taxCategoryId = [$value->id];
        } elseif ($value !== null) {
            $this->taxCategoryId = DB::table(\CraftCms\Commerce\Database\Table::TAXCATEGORIES . ' as taxcategories')
                ->whereColumn('taxcategories.id', 'commerce_purchasables.taxCategoryId')
                ->whereParam('handle', $value)
                ->select('taxcategories.id');
        } else {
            $this->taxCategoryId = null;
        }

        return $this;
    }

    public function onPromotion(?bool $value = true): static
    {
        $this->onPromotion = $value;
        return $this;
    }
}
