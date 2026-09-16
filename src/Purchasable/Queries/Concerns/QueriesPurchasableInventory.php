<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Purchasable\Queries\Concerns;

use CraftCms\Commerce\Purchasable\Queries\PurchasableQuery;
use Illuminate\Contracts\Database\Query\Builder as BuilderContract;

/**
 * Purchasable sku/stock scopes — see {@see QueriesPurchasablePricing} for the trait convention
 * this follows.
 *
 * @internal
 */
trait QueriesPurchasableInventory
{
    public mixed $sku = null;

    public mixed $stock = null;

    public ?bool $hasStock = null;

    public ?bool $inventoryTracked = null;

    public ?bool $availableForPurchase = null;

    protected function initQueriesPurchasableInventory(): void
    {
        $this->beforeQuery(static function(PurchasableQuery $purchasableQuery) {
            static::applySku($purchasableQuery, $purchasableQuery->sku);
            static::applyStock($purchasableQuery, $purchasableQuery->stock);
            static::applyInventoryTracked($purchasableQuery, $purchasableQuery->inventoryTracked);
            static::applyAvailableForPurchase($purchasableQuery, $purchasableQuery->availableForPurchase);
            static::applyHasStock($purchasableQuery, $purchasableQuery->hasStock);
        });
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

    public function inventoryTracked(?bool $value = true): static
    {
        $this->inventoryTracked = $value;
        return $this;
    }

    public function availableForPurchase(?bool $value = true): static
    {
        $this->availableForPurchase = $value;
        return $this;
    }
}
