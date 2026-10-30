<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Product\Queries\Concerns;

use CraftCms\Commerce\Product\Queries\ProductQuery;
use Illuminate\Contracts\Database\Query\Builder as BuilderContract;

/**
 * Scopes narrowing products by an attribute of their default variant — see
 * {@see \CraftCms\Commerce\Order\Queries\Concerns\QueriesOrderIdentity} for the trait convention
 * this follows.
 *
 * @internal
 */
trait QueriesProductDefaultVariant
{
    public mixed $defaultPrice = null;

    public mixed $defaultHeight = null;

    public mixed $defaultLength = null;

    public mixed $defaultWidth = null;

    public mixed $defaultWeight = null;

    public mixed $defaultSku = null;

    protected function initQueriesProductDefaultVariant(): void
    {
        $this->beforeQuery(static function(ProductQuery $productQuery) {
            static::applyDefaultPrice($productQuery, $productQuery->defaultPrice, $productQuery->hasCatalogPricingRules);
            static::applyDefaultHeight($productQuery, $productQuery->defaultHeight);
            static::applyDefaultLength($productQuery, $productQuery->defaultLength);
            static::applyDefaultWidth($productQuery, $productQuery->defaultWidth);
            static::applyDefaultWeight($productQuery, $productQuery->defaultWeight);
            static::applyDefaultSku($productQuery, $productQuery->defaultSku);
        });
    }

    public static function applyDefaultPrice(BuilderContract $query, mixed $value, bool $hasCatalogPricingRules): void
    {
        if (!isset($value)) {
            return;
        }

        $query->whereParam($hasCatalogPricingRules ? 'catalogprices.price' : 'purchasablesstores.basePrice', $value);
    }

    public static function applyDefaultHeight(BuilderContract $query, mixed $value): void
    {
        if (!isset($value)) {
            return;
        }

        $query->whereParam('purchasables.height', $value);
    }

    public static function applyDefaultLength(BuilderContract $query, mixed $value): void
    {
        if (!isset($value)) {
            return;
        }

        $query->whereParam('purchasables.length', $value);
    }

    public static function applyDefaultWidth(BuilderContract $query, mixed $value): void
    {
        if (!isset($value)) {
            return;
        }

        $query->whereParam('purchasables.width', $value);
    }

    public static function applyDefaultWeight(BuilderContract $query, mixed $value): void
    {
        if (!isset($value)) {
            return;
        }

        $query->whereParam('purchasables.weight', $value);
    }

    public static function applyDefaultSku(BuilderContract $query, mixed $value): void
    {
        if (!isset($value)) {
            return;
        }

        $query->whereParam('purchasables.sku', $value);
    }

    /**
     * Narrows the query results based on the products’ default variant price.
     */
    public function defaultPrice(mixed $value): static
    {
        $this->defaultPrice = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the products’ default variant height.
     */
    public function defaultHeight(mixed $value): static
    {
        $this->defaultHeight = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the products’ default variant length.
     */
    public function defaultLength(mixed $value): static
    {
        $this->defaultLength = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the products’ default variant width.
     */
    public function defaultWidth(mixed $value): static
    {
        $this->defaultWidth = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the products’ default variant weight.
     */
    public function defaultWeight(mixed $value): static
    {
        $this->defaultWeight = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the products’ default variant SKU.
     */
    public function defaultSku(mixed $value): static
    {
        $this->defaultSku = $value;
        return $this;
    }
}
