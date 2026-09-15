<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Purchasable\Queries\Concerns;

use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Purchasable\Queries\PurchasableQuery;
use CraftCms\Commerce\Shipping\Data\ShippingCategory;
use CraftCms\Commerce\Tax\Data\TaxCategory;
use Illuminate\Contracts\Database\Query\Builder as BuilderContract;
use Illuminate\Support\Facades\DB;

/**
 * Purchasable shipping/tax category scopes — see {@see QueriesPurchasablePricing} for the trait
 * convention this follows.
 *
 * @internal
 */
trait QueriesPurchasableCategories
{
    public mixed $shippingCategoryId = null;

    public mixed $taxCategoryId = null;

    protected function initQueriesPurchasableCategories(): void
    {
        $this->beforeQuery(static function(PurchasableQuery $purchasableQuery) {
            static::applyShippingCategoryId($purchasableQuery, $purchasableQuery->shippingCategoryId);
            static::applyTaxCategoryId($purchasableQuery, $purchasableQuery->taxCategoryId);
        });
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
            $this->shippingCategoryId = DB::table(Table::SHIPPINGCATEGORIES . ' as shippingcategories')
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
            $this->taxCategoryId = DB::table(Table::TAXCATEGORIES . ' as taxcategories')
                ->whereColumn('taxcategories.id', 'commerce_purchasables.taxCategoryId')
                ->whereParam('handle', $value)
                ->select('taxcategories.id');
        } else {
            $this->taxCategoryId = null;
        }

        return $this;
    }
}
