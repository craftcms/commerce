<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Queries\Concerns;

use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Inventory\Enums\ContainsPurchasablesMatch;
use CraftCms\Commerce\Order\Enums\OrderNoticeType;
use CraftCms\Commerce\Order\Queries\OrderQuery;
use CraftCms\Commerce\Purchasable\Contracts\PurchasableInterface;
use Illuminate\Contracts\Database\Query\Builder as BuilderContract;
use Illuminate\Database\Query\Builder;

/**
 * Order line-item/related-record scopes — see {@see QueriesOrderIdentity} for the trait convention
 * this follows.
 *
 * @internal
 */
trait QueriesOrderPurchasables
{
    public mixed $hasPurchasables = null;

    /** @var array{purchasables: array<int|PurchasableInterface>, match: ContainsPurchasablesMatch}|null */
    public ?array $containsPurchasables = null;

    public ?bool $hasTransactions = null;

    public ?bool $hasLineItems = null;

    public ?bool $hasAdminNotices = null;

    protected function initQueriesOrderPurchasables(): void
    {
        $this->beforeQuery(static function(OrderQuery $orderQuery) {
            static::applyHasPurchasables($orderQuery, $orderQuery->hasPurchasables);
            static::applyContainsPurchasables($orderQuery, $orderQuery->containsPurchasables);
            static::applyHasTransactions($orderQuery, $orderQuery->hasTransactions);
            static::applyHasLineItems($orderQuery, $orderQuery->hasLineItems);
            static::applyHasAdminNotices($orderQuery, $orderQuery->hasAdminNotices);
        });
    }

    /** @param PurchasableInterface|array<int, (int|PurchasableInterface)>|null $value */
    public static function applyHasPurchasables(BuilderContract $query, mixed $value): void
    {
        if (!isset($value)) {
            return;
        }

        $purchasables = is_array($value) ? $value : [$value];
        $purchasableIds = [];

        foreach ($purchasables as $purchasable) {
            if ($purchasable instanceof PurchasableInterface) {
                $purchasableIds[] = $purchasable->getId();
            } elseif (is_numeric($purchasable)) {
                $purchasableIds[] = $purchasable;
            }
        }

        // Remove any blank purchasable IDs (if any)
        $purchasableIds = array_filter($purchasableIds);

        $query->whereExists(function(Builder $sub) use ($purchasableIds) {
            $sub->from(Table::LINEITEMS . ' as lineitems')
                ->whereColumn('lineitems.orderId', 'elements.id')
                ->whereIn('lineitems.purchasableId', $purchasableIds);
        });
    }

    /** @param array{purchasables: array<int|PurchasableInterface>, match: ContainsPurchasablesMatch}|null $value */
    public static function applyContainsPurchasables(BuilderContract $query, ?array $value): void
    {
        if (!isset($value)) {
            return;
        }

        $purchasables = $value['purchasables'];
        $match = $value['match'];

        $purchasableIds = [];

        foreach ($purchasables as $purchasable) {
            if ($purchasable instanceof PurchasableInterface) {
                $purchasableIds[] = $purchasable->getId();
            } elseif (is_numeric($purchasable)) {
                $purchasableIds[] = $purchasable;
            }
        }

        $purchasableIds = array_values(array_filter($purchasableIds));

        if ($match === ContainsPurchasablesMatch::All || $match === ContainsPurchasablesMatch::Only) {
            // Every requested purchasable must have its own line item (AND logic)
            foreach ($purchasableIds as $id) {
                $query->whereExists(function(Builder $sub) use ($id) {
                    $sub->from(Table::LINEITEMS . ' as lineitems')
                        ->whereColumn('lineitems.orderId', 'elements.id')
                        ->where('lineitems.purchasableId', $id);
                });
            }

            if ($match === ContainsPurchasablesMatch::Only) {
                // No line items with a purchasable outside the set, and no custom line items
                $query->whereNotExists(function(Builder $sub) use ($purchasableIds) {
                    $sub->from(Table::LINEITEMS . ' as lineitems')
                        ->whereColumn('lineitems.orderId', 'elements.id')
                        ->where(function(Builder $q) use ($purchasableIds) {
                            $q->whereNull('lineitems.purchasableId')
                                ->orWhereNotIn('lineitems.purchasableId', $purchasableIds);
                        });
                });
            }
        } else {
            // ContainsPurchasablesMatch::Any: at least one of the purchasables must be in the order
            $query->whereExists(function(Builder $sub) use ($purchasableIds) {
                $sub->from(Table::LINEITEMS . ' as lineitems')
                    ->whereColumn('lineitems.orderId', 'elements.id')
                    ->whereIn('lineitems.purchasableId', $purchasableIds);
            });
        }
    }

    public static function applyHasTransactions(BuilderContract $query, ?bool $value): void
    {
        // Allow true or false but not null
        if (!isset($value)) {
            return;
        }

        $method = $value ? 'whereExists' : 'whereNotExists';
        $query->$method(function(Builder $sub) {
            $sub->from(Table::TRANSACTIONS . ' as transactions')
                ->whereColumn('transactions.orderId', 'elements.id');
        });
    }

    public static function applyHasLineItems(BuilderContract $query, ?bool $value): void
    {
        // Allow true or false but not null
        if (!isset($value)) {
            return;
        }

        $method = $value ? 'whereExists' : 'whereNotExists';
        $query->$method(function(Builder $sub) {
            $sub->from(Table::LINEITEMS . ' as lineitems')
                ->whereColumn('lineitems.orderId', 'elements.id');
        });
    }

    public static function applyHasAdminNotices(BuilderContract $query, ?bool $value): void
    {
        if (!isset($value)) {
            return;
        }

        $method = $value ? 'whereExists' : 'whereNotExists';
        $query->$method(function(Builder $sub) {
            $sub->from(Table::ORDERNOTICES . ' as adminNotices')
                ->whereColumn('adminNotices.orderId', 'elements.id')
                ->where('adminNotices.noticeType', OrderNoticeType::Admin->value);
        });
    }

    public function hasLineItems(?bool $value = true): static
    {
        $this->hasLineItems = $value;
        return $this;
    }

    public function hasAdminNotices(?bool $value = true): static
    {
        $this->hasAdminNotices = $value;
        return $this;
    }

    public function hasTransactions(?bool $value = true): static
    {
        $this->hasTransactions = $value;
        return $this;
    }

    /** @param PurchasableInterface|array<int, (int|PurchasableInterface)>|null $value */
    public function hasPurchasables(mixed $value): static
    {
        $this->hasPurchasables = $value;
        return $this;
    }

    /** @param array{purchasables: array<int|PurchasableInterface>, match: ContainsPurchasablesMatch} $value */
    public function containsPurchasables(array $value): static
    {
        $this->containsPurchasables = $value;
        return $this;
    }
}
