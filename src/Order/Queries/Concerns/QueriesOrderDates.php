<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Queries\Concerns;

use CraftCms\Commerce\Order\Queries\OrderQuery;
use Illuminate\Contracts\Database\Query\Builder as BuilderContract;

/**
 * Order date scopes — see {@see QueriesOrderIdentity} for the trait convention this follows.
 *
 * @internal
 */
trait QueriesOrderDates
{
    public mixed $dateOrdered = null;

    public mixed $expiryDate = null;

    public mixed $datePaid = null;

    public mixed $dateFirstPaid = null;

    public mixed $dateAuthorized = null;

    protected function initQueriesOrderDates(): void
    {
        $this->beforeQuery(static function(OrderQuery $orderQuery) {
            static::applyDateAuthorized($orderQuery, $orderQuery->dateAuthorized);
            static::applyDateOrdered($orderQuery, $orderQuery->dateOrdered);
            static::applyDatePaid($orderQuery, $orderQuery->datePaid);
            static::applyDateFirstPaid($orderQuery, $orderQuery->dateFirstPaid);
            static::applyExpiryDate($orderQuery, $orderQuery->expiryDate);
        });
    }

    public static function applyDateAuthorized(BuilderContract $query, mixed $value): void
    {
        if (!isset($value)) {
            return;
        }

        $query->whereDateParam('commerce_orders.dateAuthorized', $value);
    }

    public static function applyDateOrdered(BuilderContract $query, mixed $value): void
    {
        if (!isset($value)) {
            return;
        }

        $query->whereDateParam('commerce_orders.dateOrdered', $value);
    }

    public static function applyDatePaid(BuilderContract $query, mixed $value): void
    {
        if (!isset($value)) {
            return;
        }

        $query->whereDateParam('commerce_orders.datePaid', $value);
    }

    public static function applyDateFirstPaid(BuilderContract $query, mixed $value): void
    {
        if (!isset($value)) {
            return;
        }

        $query->whereDateParam('commerce_orders.dateFirstPaid', $value);
    }

    public static function applyExpiryDate(BuilderContract $query, mixed $value): void
    {
        if (!isset($value)) {
            return;
        }

        $query->whereDateParam('commerce_orders.expiryDate', $value);
    }

    public function dateOrdered(mixed $value): static
    {
        $this->dateOrdered = $value;
        return $this;
    }

    public function datePaid(mixed $value): static
    {
        $this->datePaid = $value;
        return $this;
    }

    public function dateFirstPaid(mixed $value): static
    {
        $this->dateFirstPaid = $value;
        return $this;
    }

    public function dateAuthorized(mixed $value): static
    {
        $this->dateAuthorized = $value;
        return $this;
    }

    public function expiryDate(mixed $value): static
    {
        $this->expiryDate = $value;
        return $this;
    }
}
