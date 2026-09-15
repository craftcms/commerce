<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Queries\Concerns;

use CraftCms\Cms\Database\Table as CraftTable;
use CraftCms\Cms\Element\Queries\Exceptions\QueryAbortedException;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Inventory\Enums\ContainsPurchasablesMatch;
use CraftCms\Commerce\Order\Data\OrderStatus;
use CraftCms\Commerce\Order\Enums\OrderNoticeType;
use CraftCms\Commerce\Order\Queries\OrderQuery;
use CraftCms\Commerce\Payment\Gateway\Contracts\GatewayInterface;
use CraftCms\Commerce\Purchasable\Contracts\PurchasableInterface;
use Illuminate\Contracts\Database\Query\Builder as BuilderContract;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Tpetry\QueryExpressions\Language\Alias;

/**
 * Order-specific scope properties, mirroring the `QueriesFields`/`QueriesAssetLocation` convention
 * from craft core: each scope has a public property, a fluent setter, and a public static `apply*()`
 * method that writes directly to a `Builder` — condition rules should always call the static method
 * directly (see `CraftCms\Commerce\Order\Conditions\*`), never the fluent setter, since the fluent
 * setter's value is only consumed by the `beforeQuery()` callback registered below, which — being
 * registered in the constructor — always runs *before* a condition rule (applied later, via a
 * separately-registered `beforeQuery()` callback of its own) would have a chance to set it.
 *
 * @internal
 */
trait QueriesOrderAttributes
{
    public mixed $number = null;

    public mixed $shortNumber = null;

    public mixed $reference = null;

    public mixed $couponCode = null;

    public mixed $email = null;

    public ?bool $isCompleted = null;

    public mixed $dateOrdered = null;

    public mixed $expiryDate = null;

    public mixed $datePaid = null;

    public mixed $dateFirstPaid = null;

    public mixed $dateAuthorized = null;

    public mixed $orderStatusId = null;

    public mixed $orderLanguage = null;

    public mixed $orderSiteId = null;

    public mixed $origin = null;

    public mixed $customerId = null;

    public mixed $gatewayId = null;

    public ?int $storeId = null;

    public mixed $total = null;

    public mixed $totalPrice = null;

    public mixed $totalPaid = null;

    public mixed $totalQty = null;

    public mixed $totalWeight = null;

    public mixed $totalDiscount = null;

    public mixed $totalTax = null;

    public mixed $itemTotal = null;

    public mixed $itemSubtotal = null;

    public mixed $shippingMethodHandle = null;

    public ?bool $isPaid = null;

    public ?bool $isUnpaid = null;

    public mixed $hasPurchasables = null;

    /** @var array{purchasables: array<int|PurchasableInterface>, match: ContainsPurchasablesMatch}|null */
    public ?array $containsPurchasables = null;

    public ?bool $hasTransactions = null;

    public ?bool $hasLineItems = null;

    public ?bool $hasAdminNotices = null;

    protected function initQueriesOrderAttributes(): void
    {
        $this->beforeQuery(function(OrderQuery $query) {
            static::applyNumber($query, $query->number);
            static::applyShortNumber($query, $query->shortNumber);
            static::applyStoreId($query, $query->storeId);
            static::applyOrigin($query, $query->origin);
            static::applyReference($query, $query->reference);
            static::applyCouponCode($query, $query->couponCode);
            static::applyEmail($query, $query->email);
            static::applyIsCompleted($query, $query->isCompleted);
            static::applyDateAuthorized($query, $query->datePaid);
            static::applyDateOrdered($query, $query->dateOrdered);
            static::applyDatePaid($query, $query->datePaid);
            static::applyDateFirstPaid($query, $query->dateFirstPaid);
            static::applyExpiryDate($query, $query->expiryDate);
            static::applyOrderStatusId($query, $query->orderStatusId);
            static::applyShippingMethodHandle($query, $query->shippingMethodHandle);
            static::applyOrderLanguage($query, $query->orderLanguage);
            static::applyOrderSiteId($query, $query->orderSiteId);
            static::applyCustomerId($query, $query->customerId);
            static::applyGatewayId($query, $query->gatewayId);
            static::applyTotal($query, $query->total);
            static::applyTotalPrice($query, $query->totalPrice);
            static::applyTotalPaid($query, $query->totalPaid);
            static::applyItemTotal($query, $query->itemTotal);
            static::applyItemSubtotal($query, $query->itemSubtotal);
            static::applyTotalQty($query, $query->totalQty);
            static::applyTotalWeight($query, $query->totalWeight);
            static::applyTotalDiscount($query, $query->totalDiscount);
            static::applyTotalTax($query, $query->totalTax);
            static::applyIsPaid($query, $query->isPaid);
            static::applyIsUnpaid($query, $query->isUnpaid);
            static::applyHasPurchasables($query, $query->hasPurchasables);
            static::applyContainsPurchasables($query, $query->containsPurchasables);
            static::applyHasTransactions($query, $query->hasTransactions);
            static::applyHasLineItems($query, $query->hasLineItems);
            static::applyHasAdminNotices($query, $query->hasAdminNotices);
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

    public static function applyStoreId(BuilderContract $query, mixed $value): void
    {
        if (!isset($value) || !$value) {
            return;
        }

        $query->whereParam('commerce_orders.storeId', $value);
    }

    public static function applyOrigin(BuilderContract $query, mixed $value): void
    {
        if (!isset($value) || !$value) {
            return;
        }

        $query->whereParam('commerce_orders.origin', $value);
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

    public static function applyEmail(BuilderContract $query, mixed $value): void
    {
        if (!isset($value) || !$value) {
            return;
        }

        // Join and search the users table for email address
        $query->leftJoin(new Alias(CraftTable::USERS, 'users'), 'users.id', '=', 'commerce_orders.customerId');
        $query->whereParam('users.email', $value, caseInsensitive: true);
    }

    public static function applyIsCompleted(BuilderContract $query, ?bool $value): void
    {
        if (!isset($value)) {
            return;
        }

        $query->whereBooleanParam('commerce_orders.isCompleted', $value, false);
    }

    /**
     * NOTE: ported verbatim from the legacy Yii2 query, which filters `dateAuthorized` by the
     * value of `datePaid` (not `dateAuthorized`). This looks like a pre-existing bug, but is
     * preserved for behavioral parity; worth revisiting separately.
     */
    public static function applyDateAuthorized(BuilderContract $query, mixed $datePaidValue): void
    {
        if (!isset($datePaidValue)) {
            return;
        }

        $query->whereDateParam('commerce_orders.dateAuthorized', $datePaidValue);
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

    public static function applyShippingMethodHandle(BuilderContract $query, mixed $value): void
    {
        if (!isset($value)) {
            return;
        }

        $query->whereParam('commerce_orders.shippingMethodHandle', $value);
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

    public static function applyCustomerId(BuilderContract $query, mixed $value): void
    {
        if (!isset($value)) {
            return;
        }

        $query->whereParam('commerce_orders.customerId', $value);
    }

    public static function applyGatewayId(BuilderContract $query, mixed $value): void
    {
        if (!isset($value)) {
            return;
        }

        $query->whereParam('commerce_orders.gatewayId', $value);
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

    public function email(mixed $value): static
    {
        $this->email = $value;
        return $this;
    }

    public function isCompleted(?bool $value = true): static
    {
        $this->isCompleted = $value;
        return $this;
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

    public function shippingMethodHandle(mixed $value): static
    {
        $this->shippingMethodHandle = $value;
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

    public function origin(mixed $value): static
    {
        $this->origin = $value;
        return $this;
    }

    public function customer(int|User|null $value): static
    {
        $this->customerId = $value instanceof User ? $value->id : $value;
        return $this;
    }

    public function customerId(mixed $value): static
    {
        $this->customerId = $value;
        return $this;
    }

    public function gateway(?GatewayInterface $value): static
    {
        $this->gatewayId = $value?->id;
        return $this;
    }

    public function gatewayId(mixed $value): static
    {
        $this->gatewayId = $value;
        return $this;
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

    public function storeId(?int $value): static
    {
        $this->storeId = $value;
        return $this;
    }
}
