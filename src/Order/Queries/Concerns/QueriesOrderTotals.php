<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Queries\Concerns;

use CraftCms\Commerce\Order\Queries\OrderQuery;
use Illuminate\Contracts\Database\Query\Builder as BuilderContract;

/**
 * Order monetary/quantity total scopes — see {@see QueriesOrderIdentity} for the trait convention
 * this follows.
 *
 * @internal
 */
trait QueriesOrderTotals
{
    public mixed $total = null;

    public mixed $totalPrice = null;

    public mixed $totalPaid = null;

    public mixed $totalQty = null;

    public mixed $totalWeight = null;

    public mixed $totalDiscount = null;

    public mixed $totalTax = null;

    public mixed $itemTotal = null;

    public mixed $itemSubtotal = null;

    protected function initQueriesOrderTotals(): void
    {
        $this->beforeQuery(static function(OrderQuery $orderQuery) {
            static::applyTotal($orderQuery, $orderQuery->total);
            static::applyTotalPrice($orderQuery, $orderQuery->totalPrice);
            static::applyTotalPaid($orderQuery, $orderQuery->totalPaid);
            static::applyItemTotal($orderQuery, $orderQuery->itemTotal);
            static::applyItemSubtotal($orderQuery, $orderQuery->itemSubtotal);
            static::applyTotalQty($orderQuery, $orderQuery->totalQty);
            static::applyTotalWeight($orderQuery, $orderQuery->totalWeight);
            static::applyTotalDiscount($orderQuery, $orderQuery->totalDiscount);
            static::applyTotalTax($orderQuery, $orderQuery->totalTax);
        });
    }

    public static function applyTotal(BuilderContract $query, mixed $value): void
    {
        if (!isset($value)) {
            return;
        }

        $query->whereParam('commerce_orders.total', $value);
    }

    public static function applyTotalPrice(BuilderContract $query, mixed $value): void
    {
        if (!isset($value)) {
            return;
        }

        $query->whereParam('commerce_orders.totalPrice', $value);
    }

    public static function applyTotalPaid(BuilderContract $query, mixed $value): void
    {
        if (!isset($value)) {
            return;
        }

        $query->whereParam('commerce_orders.totalPaid', $value);
    }

    public static function applyItemTotal(BuilderContract $query, mixed $value): void
    {
        if (!isset($value)) {
            return;
        }

        $query->whereParam('commerce_orders.itemTotal', $value);
    }

    public static function applyItemSubtotal(BuilderContract $query, mixed $value): void
    {
        if (!isset($value)) {
            return;
        }

        $query->whereParam('commerce_orders.itemSubtotal', $value);
    }

    public static function applyTotalQty(BuilderContract $query, mixed $value): void
    {
        if (!isset($value)) {
            return;
        }

        $query->whereParam('commerce_orders.totalQty', $value);
    }

    public static function applyTotalWeight(BuilderContract $query, mixed $value): void
    {
        if (!isset($value)) {
            return;
        }

        $query->whereParam('commerce_orders.totalWeight', $value);
    }

    public static function applyTotalDiscount(BuilderContract $query, mixed $value): void
    {
        if (!isset($value)) {
            return;
        }

        $query->whereParam('commerce_orders.totalDiscount', $value);
    }

    public static function applyTotalTax(BuilderContract $query, mixed $value): void
    {
        if (!isset($value)) {
            return;
        }

        $query->whereParam('commerce_orders.totalTax', $value);
    }

    public function total(mixed $value): static
    {
        $this->total = $value;
        return $this;
    }

    public function totalPrice(mixed $value): static
    {
        $this->totalPrice = $value;
        return $this;
    }

    public function totalPaid(mixed $value): static
    {
        $this->totalPaid = $value;
        return $this;
    }

    public function totalQty(mixed $value): static
    {
        $this->totalQty = $value;
        return $this;
    }

    public function totalWeight(mixed $value): static
    {
        $this->totalWeight = $value;
        return $this;
    }

    public function totalDiscount(mixed $value): static
    {
        $this->totalDiscount = $value;
        return $this;
    }

    public function totalTax(mixed $value): static
    {
        $this->totalTax = $value;
        return $this;
    }

    public function itemTotal(mixed $value): static
    {
        $this->itemTotal = $value;
        return $this;
    }

    public function itemSubtotal(mixed $value): static
    {
        $this->itemSubtotal = $value;
        return $this;
    }
}
