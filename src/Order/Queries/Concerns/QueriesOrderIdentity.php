<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Queries\Concerns;

use CraftCms\Cms\Element\Queries\Exceptions\QueryAbortedException;
use CraftCms\Commerce\Order\Queries\OrderQuery;
use Illuminate\Contracts\Database\Query\Builder as BuilderContract;

/**
 * Order identifying/scoping attributes, mirroring the `QueriesFields`/`QueriesAssetLocation`
 * convention from craft core: each scope has a public property, a fluent setter, and a public
 * static `apply*()` method that writes directly to a `Builder` — condition rules should always
 * call the static method directly (see `CraftCms\Commerce\Order\Conditions\*`), never the fluent
 * setter, since the fluent setter's value is only consumed by the `beforeQuery()` callback
 * registered below, which — being registered in the constructor — always runs *before* a condition
 * rule (applied later, via a separately-registered `beforeQuery()` callback of its own) would have
 * a chance to set it.
 *
 * @internal
 */
trait QueriesOrderIdentity
{
    public mixed $number = null;

    public mixed $shortNumber = null;

    public mixed $reference = null;

    public mixed $couponCode = null;

    public mixed $origin = null;

    public ?int $storeId = null;

    public mixed $orderLanguage = null;

    public mixed $orderSiteId = null;

    protected function initQueriesOrderIdentity(): void
    {
        $this->beforeQuery(static function(OrderQuery $orderQuery) {
            static::applyNumber($orderQuery, $orderQuery->number);
            static::applyShortNumber($orderQuery, $orderQuery->shortNumber);
            static::applyReference($orderQuery, $orderQuery->reference);
            static::applyCouponCode($orderQuery, $orderQuery->couponCode);
            static::applyOrigin($orderQuery, $orderQuery->origin);
            static::applyStoreId($orderQuery, $orderQuery->storeId);
            static::applyOrderLanguage($orderQuery, $orderQuery->orderLanguage);
            static::applyOrderSiteId($orderQuery, $orderQuery->orderSiteId);
        });
    }

    public static function applyNumber(BuilderContract $query, mixed $value): void
    {
        if (!isset($value)) {
            return;
        }

        // If it's set to anything besides a non-empty string, abort the query
        if (!is_string($value) || $value === '') {
            throw new QueryAbortedException();
        }

        $query->where('commerce_orders.number', $value);
    }

    public static function applyShortNumber(BuilderContract $query, mixed $value): void
    {
        if (!isset($value)) {
            return;
        }

        // If it's set to anything besides a non-empty string, abort the query
        if (!is_string($value) || $value === '') {
            throw new QueryAbortedException();
        }

        $query->whereRaw('LEFT(commerce_orders.number, 7) = ?', [$value]);
    }

    public static function applyReference(BuilderContract $query, mixed $value): void
    {
        if (!isset($value) || !$value) {
            return;
        }

        $query->whereParam('commerce_orders.reference', $value);
    }

    public static function applyCouponCode(BuilderContract $query, mixed $value): void
    {
        if (!isset($value)) {
            return;
        }

        // Coupon code criteria is case-insensitive like in the adjuster
        $query->whereParam('commerce_orders.couponCode', $value, caseInsensitive: true);
    }

    public static function applyOrigin(BuilderContract $query, mixed $value): void
    {
        if (!isset($value) || !$value) {
            return;
        }

        $query->whereParam('commerce_orders.origin', $value);
    }

    public static function applyStoreId(BuilderContract $query, mixed $value): void
    {
        if (!isset($value) || !$value) {
            return;
        }

        $query->whereParam('commerce_orders.storeId', $value);
    }

    public static function applyOrderLanguage(BuilderContract $query, mixed $value): void
    {
        if (!isset($value)) {
            return;
        }

        $query->whereParam('commerce_orders.orderLanguage', $value);
    }

    public static function applyOrderSiteId(BuilderContract $query, mixed $value): void
    {
        if (!isset($value)) {
            return;
        }

        $query->whereParam('commerce_orders.orderSiteId', $value);
    }

    public function number(mixed $value): static
    {
        $this->number = $value;
        return $this;
    }

    public function shortNumber(mixed $value): static
    {
        $this->shortNumber = $value;
        return $this;
    }

    public function reference(mixed $value): static
    {
        $this->reference = $value;
        return $this;
    }

    public function couponCode(mixed $value): static
    {
        $this->couponCode = $value;
        return $this;
    }

    public function origin(mixed $value): static
    {
        $this->origin = $value;
        return $this;
    }

    public function storeId(?int $value): static
    {
        $this->storeId = $value;
        return $this;
    }

    public function orderLanguage(mixed $value): static
    {
        $this->orderLanguage = $value;
        return $this;
    }

    public function orderSiteId(mixed $value): static
    {
        $this->orderSiteId = $value;
        return $this;
    }
}
