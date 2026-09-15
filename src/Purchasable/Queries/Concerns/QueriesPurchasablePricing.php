<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Purchasable\Queries\Concerns;

use CraftCms\Commerce\Purchasable\Queries\PurchasableQuery;
use Illuminate\Contracts\Database\Query\Builder as BuilderContract;
use Illuminate\Support\Facades\DB;

/**
 * Purchasable price scopes, mirroring the `QueriesFields`/`QueriesAssetLocation` convention from
 * craft core — see {@see \CraftCms\Commerce\Order\Queries\Concerns\QueriesOrderIdentity} for why
 * condition rules must call the static `apply*()` methods directly rather than the fluent setters.
 *
 * @internal
 */
trait QueriesPurchasablePricing
{
    public mixed $price = null;

    public mixed $promotionalPrice = null;

    public ?bool $onPromotion = null;

    public mixed $salePrice = null;

    protected function initQueriesPurchasablePricing(): void
    {
        $this->beforeQuery(static function(PurchasableQuery $purchasableQuery) {
            // $purchasableQuery->hasCatalogPricingRules is captured once, at construction time, and
            // must stay in sync with the decision the constructor already made about which
            // columns/joins (catalogprices.* vs purchasables_stores.*) are actually available on
            // this query — recomputing it here could disagree with that decision and reference a
            // nonexistent alias.
            static::applyPrice($purchasableQuery, $purchasableQuery->price, $purchasableQuery->hasCatalogPricingRules);
            static::applyPromotionalPrice($purchasableQuery, $purchasableQuery->promotionalPrice, $purchasableQuery->hasCatalogPricingRules);
            static::applyOnPromotion($purchasableQuery, $purchasableQuery->onPromotion, $purchasableQuery->hasCatalogPricingRules);
            static::applySalePrice($purchasableQuery, $purchasableQuery->salePrice, $purchasableQuery->hasCatalogPricingRules);
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

    public function price(mixed $value): static
    {
        $this->price = $value;
        return $this;
    }

    public function promotionalPrice(mixed $value): static
    {
        $this->promotionalPrice = $value;
        return $this;
    }

    public function onPromotion(?bool $value = true): static
    {
        $this->onPromotion = $value;
        return $this;
    }

    public function salePrice(mixed $value): static
    {
        $this->salePrice = $value;
        return $this;
    }
}
