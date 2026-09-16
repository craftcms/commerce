<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Product\Queries\Concerns;

use CraftCms\Cms\Support\Arr;
use CraftCms\Commerce\Product\Queries\ProductQuery;
use DateTime;
use Illuminate\Contracts\Database\Query\Builder as BuilderContract;

/**
 * Product post/expiry date scopes, mirroring {@see \CraftCms\Cms\Element\Queries\Concerns\Entry\QueriesEntryDates}
 * — see {@see \CraftCms\Commerce\Order\Queries\Concerns\QueriesOrderIdentity} for the trait
 * convention this follows.
 *
 * @internal
 */
trait QueriesProductDates
{
    public mixed $postDate = null;

    public mixed $expiryDate = null;

    protected function initQueriesProductDates(): void
    {
        $this->beforeQuery(static function(ProductQuery $productQuery) {
            static::applyPostDate($productQuery, $productQuery->postDate);
            static::applyExpiryDate($productQuery, $productQuery->expiryDate);
        });
    }

    public static function applyPostDate(BuilderContract $query, mixed $value): void
    {
        if (!isset($value)) {
            return;
        }

        $query->whereDateParam('commerce_products.postDate', $value);
    }

    public static function applyExpiryDate(BuilderContract $query, mixed $value): void
    {
        if (!isset($value)) {
            return;
        }

        $query->whereDateParam('commerce_products.expiryDate', $value);
    }

    /**
     * Narrows the query results based on the products’ post dates.
     */
    public function postDate(mixed $value): static
    {
        $this->postDate = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the products’ expiry dates.
     */
    public function expiryDate(mixed $value): static
    {
        $this->expiryDate = $value;
        return $this;
    }

    /**
     * Narrows the query results to only products that were posted before a certain date.
     */
    public function before(DateTime|string $value): static
    {
        if ($value instanceof DateTime) {
            $value = $value->format(DateTime::W3C);
        }

        $this->postDate = Arr::wrap($this->postDate);
        $this->postDate[] = '<' . $value;

        return $this;
    }

    /**
     * Narrows the query results to only products that were posted on or after a certain date.
     */
    public function after(DateTime|string $value): static
    {
        if ($value instanceof DateTime) {
            $value = $value->format(DateTime::W3C);
        }

        $this->postDate = Arr::wrap($this->postDate);
        $this->postDate[] = '>=' . $value;

        return $this;
    }
}
