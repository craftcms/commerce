<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Queries\Concerns;

use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Order\Data\OrderStatus;
use CraftCms\Commerce\Order\Queries\OrderQuery;
use Illuminate\Contracts\Database\Query\Builder as BuilderContract;
use Illuminate\Support\Facades\DB;

/**
 * Order status/payment-state scopes — see {@see QueriesOrderIdentity} for the trait convention
 * this follows.
 *
 * @internal
 */
trait QueriesOrderStatus
{
    public mixed $orderStatusId = null;

    public ?bool $isCompleted = null;

    public ?bool $isPaid = null;

    public ?bool $isUnpaid = null;

    protected function initQueriesOrderStatus(): void
    {
        $this->beforeQuery(static function(OrderQuery $orderQuery) {
            static::applyIsCompleted($orderQuery, $orderQuery->isCompleted);
            static::applyOrderStatusId($orderQuery, $orderQuery->orderStatusId);
            static::applyIsPaid($orderQuery, $orderQuery->isPaid);
            static::applyIsUnpaid($orderQuery, $orderQuery->isUnpaid);
        });
    }

    public static function applyIsCompleted(BuilderContract $query, ?bool $value): void
    {
        if (!isset($value)) {
            return;
        }

        $query->whereBooleanParam('commerce_orders.isCompleted', $value, false);
    }

    public static function applyOrderStatusId(BuilderContract $query, mixed $value): void
    {
        if (!isset($value)) {
            return;
        }

        $query->whereParam('commerce_orders.orderStatusId', $value);
    }

    /** @param string|string[]|OrderStatus|null $value */
    public static function applyOrderStatus(BuilderContract $query, mixed $value): void
    {
        if ($value instanceof OrderStatus) {
            $orderStatusId = $value->id;
        } elseif ($value !== null) {
            $orderStatusId = DB::table(Table::ORDERSTATUSES)
                ->whereParam('handle', $value)
                ->pluck('id')
                ->all();
        } else {
            $orderStatusId = null;
        }

        static::applyOrderStatusId($query, $orderStatusId);
    }

    public static function applyIsPaid(BuilderContract $query, ?bool $value): void
    {
        // Allow true but not null
        if (!isset($value) || !$value) {
            return;
        }

        $query->whereColumn('commerce_orders.totalPaid', '>=', 'commerce_orders.totalPrice');
    }

    public static function applyIsUnpaid(BuilderContract $query, ?bool $value): void
    {
        // Allow true but not null
        if (!isset($value) || !$value) {
            return;
        }

        $query->whereColumn('commerce_orders.totalPaid', '<', 'commerce_orders.totalPrice');
    }

    public function isCompleted(?bool $value = true): static
    {
        $this->isCompleted = $value;
        return $this;
    }

    /** @param string|string[]|OrderStatus|null $value */
    public function orderStatus(mixed $value): static
    {
        if ($value instanceof OrderStatus) {
            $this->orderStatusId = $value->id;
        } elseif ($value !== null) {
            $this->orderStatusId = DB::table(Table::ORDERSTATUSES)
                ->whereParam('handle', $value)
                ->pluck('id')
                ->all();
        } else {
            $this->orderStatusId = null;
        }

        return $this;
    }

    public function orderStatusId(mixed $value): static
    {
        $this->orderStatusId = $value;
        return $this;
    }

    public function isPaid(?bool $value = true): static
    {
        $this->isPaid = $value;
        return $this;
    }

    public function isUnpaid(?bool $value = true): static
    {
        $this->isUnpaid = $value;
        return $this;
    }
}
