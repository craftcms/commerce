<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Queries;

use CraftCms\Cms\Database\Table as CraftTable;
use CraftCms\Cms\Element\Queries\ElementQuery;
use CraftCms\Cms\Element\Queries\Exceptions\QueryAbortedException;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Customer\Customers;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Inventory\Enums\ContainsPurchasablesMatch;
use CraftCms\Commerce\Order\Data\OrderStatus;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\Enums\OrderNoticeType;
use CraftCms\Commerce\Order\LineItem\LineItems;
use CraftCms\Commerce\Order\OrderAdjustments;
use CraftCms\Commerce\Order\OrderNotices;
use CraftCms\Commerce\Order\Orders;
use CraftCms\Commerce\Payment\Gateway\Contracts\GatewayInterface;
use CraftCms\Commerce\Payment\Transactions;
use CraftCms\Commerce\Purchasable\Contracts\PurchasableInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Override;
use Tpetry\QueryExpressions\Language\Alias;

/**
 * @extends ElementQuery<Order>
 */
class OrderQuery extends ElementQuery
{
    protected string $table = Table::ORDERS;

    /** @var array<string, int> */
    protected array $defaultOrderBy = [
        'commerce_orders.id' => SORT_ASC,
    ];

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

    public bool $withAll = false;

    public bool $withAddresses = false;

    public bool $withAdjustments = false;

    public bool $withCustomer = false;

    public bool $withLineItems = false;

    public bool $withTransactions = false;

    /** @param array<string, mixed> $config */
    public function __construct(array $config = [])
    {
        parent::__construct(Order::class, $config);

        $this->query->addSelect([
            'commerce_orders.storeId',
            'commerce_orders.number',
            'commerce_orders.reference',
            'commerce_orders.couponCode',
            'commerce_orders.orderStatusId',
            'commerce_orders.dateOrdered',

            // `commerce_orders.email` is deliberately not selected: the customer's email now lives
            // on the customer relation, not this column. The `email()` scope below still filters by
            // it via a join to the users table.
            'commerce_orders.isCompleted',
            'commerce_orders.datePaid',
            'commerce_orders.dateFirstPaid',
            'commerce_orders.currency',
            'commerce_orders.paymentCurrency',
            'commerce_orders.lastIp',
            'commerce_orders.orderLanguage',
            'commerce_orders.message',
            'commerce_orders.returnUrl',
            'commerce_orders.cancelUrl',
            'commerce_orders.billingAddressId',
            'commerce_orders.shippingAddressId',
            'commerce_orders.estimatedBillingAddressId',
            'commerce_orders.estimatedShippingAddressId',
            'commerce_orders.sourceBillingAddressId',
            'commerce_orders.sourceShippingAddressId',
            'commerce_orders.shippingMethodHandle',
            'commerce_orders.gatewayId',
            'commerce_orders.paymentSourceId',
            'commerce_orders.customerId',
            'commerce_orders.customerDeleted',
            'commerce_orders.dateUpdated',
            'commerce_orders.registerUserOnOrderComplete',
            'commerce_orders.saveBillingAddressOnOrderComplete',
            'commerce_orders.saveShippingAddressOnOrderComplete',
            'commerce_orders.makePrimaryShippingAddress',
            'commerce_orders.makePrimaryBillingAddress',
            'commerce_orders.recalculationMode',
            'commerce_orders.origin',
            'commerce_orders.dateAuthorized',
            'commerce_orders.totalPrice as storedTotalPrice',
            'commerce_orders.totalPaid as storedTotalPaid',
            'commerce_orders.itemTotal as storedItemTotal',
            'commerce_orders.totalDiscount as storedTotalDiscount',
            'commerce_orders.totalShippingCost as storedTotalShippingCost',
            'commerce_orders.totalTax as storedTotalTax',
            'commerce_orders.totalTaxIncluded as storedTotalTaxIncluded',
            'commerce_orders.itemSubtotal as storedItemSubtotal',
            'commerce_orders.totalQty as storedTotalQty',
            'commerce_orders.shippingMethodName',
            'commerce_orders.orderSiteId',
            'commerce_orders.orderCompletedEmail',
        ]);

        // Addresses joined for sorting/filtering purposes.
        $this->query->leftJoin(new Alias(CraftTable::ADDRESSES, 'billing_address'), 'billing_address.id', '=', 'commerce_orders.billingAddressId');
        $this->query->leftJoin(new Alias(CraftTable::ADDRESSES, 'shipping_address'), 'shipping_address.id', '=', 'commerce_orders.shippingAddressId');

        $this->beforeQuery(function(self $query) {
            if (isset($query->number)) {
                // If it's set to anything besides a non-empty string, abort the query
                if (!is_string($query->number) || $query->number === '') {
                    throw new QueryAbortedException();
                }

                $query->where('commerce_orders.number', $query->number);
            }

            if (isset($query->shortNumber)) {
                // If it's set to anything besides a non-empty string, abort the query
                if (!is_string($query->shortNumber) || $query->shortNumber === '') {
                    throw new QueryAbortedException();
                }

                $query->whereRaw('LEFT(commerce_orders.number, 7) = ?', [$query->shortNumber]);
            }

            if (isset($query->storeId) && $query->storeId) {
                $query->whereParam('commerce_orders.storeId', $query->storeId);
            }

            if (isset($query->origin) && $query->origin) {
                $query->whereParam('commerce_orders.origin', $query->origin);
            }

            if (isset($query->reference) && $query->reference) {
                $query->whereParam('commerce_orders.reference', $query->reference);
            }

            if (isset($query->couponCode)) {
                // Coupon code criteria is case-insensitive like in the adjuster
                $query->whereParam('commerce_orders.couponCode', $query->couponCode, caseInsensitive: true);
            }

            if (isset($query->email) && $query->email) {
                // Join and search the users table for email address
                $query->leftJoin(new Alias(CraftTable::USERS, 'users'), 'users.id', '=', 'commerce_orders.customerId');
                $query->whereParam('users.email', $query->email, caseInsensitive: true);
            }

            if (isset($query->isCompleted)) {
                $query->whereBooleanParam('commerce_orders.isCompleted', $query->isCompleted, false);
            }

            // NOTE: ported verbatim from the legacy Yii2 query, which filters `dateAuthorized` by the
            // value of `datePaid` here (not `dateAuthorized`). This looks like a pre-existing bug, but
            // is preserved for behavioral parity; worth revisiting separately.
            if (isset($query->dateAuthorized)) {
                $query->whereDateParam('commerce_orders.dateAuthorized', $query->datePaid);
            }

            if (isset($query->dateOrdered)) {
                $query->whereDateParam('commerce_orders.dateOrdered', $query->dateOrdered);
            }

            if (isset($query->datePaid)) {
                $query->whereDateParam('commerce_orders.datePaid', $query->datePaid);
            }

            if (isset($query->dateFirstPaid)) {
                $query->whereDateParam('commerce_orders.dateFirstPaid', $query->dateFirstPaid);
            }

            if (isset($query->expiryDate)) {
                $query->whereDateParam('commerce_orders.expiryDate', $query->expiryDate);
            }

            if (isset($query->orderStatusId)) {
                $query->whereParam('commerce_orders.orderStatusId', $query->orderStatusId);
            }

            if (isset($query->shippingMethodHandle)) {
                $query->whereParam('commerce_orders.shippingMethodHandle', $query->shippingMethodHandle);
            }

            if (isset($query->orderLanguage)) {
                $query->whereParam('commerce_orders.orderLanguage', $query->orderLanguage);
            }

            if (isset($query->orderSiteId)) {
                $query->whereParam('commerce_orders.orderSiteId', $query->orderSiteId);
            }

            if (isset($query->customerId)) {
                $query->whereParam('commerce_orders.customerId', $query->customerId);
            }

            if (isset($query->gatewayId)) {
                $query->whereParam('commerce_orders.gatewayId', $query->gatewayId);
            }

            if (isset($query->total)) {
                $query->whereParam('commerce_orders.total', $query->total);
            }

            if (isset($query->totalPrice)) {
                $query->whereParam('commerce_orders.totalPrice', $query->totalPrice);
            }

            if (isset($query->totalPaid)) {
                $query->whereParam('commerce_orders.totalPaid', $query->totalPaid);
            }

            if (isset($query->itemTotal)) {
                $query->whereParam('commerce_orders.itemTotal', $query->itemTotal);
            }

            if (isset($query->itemSubtotal)) {
                $query->whereParam('commerce_orders.itemSubtotal', $query->itemSubtotal);
            }

            if (isset($query->totalQty)) {
                $query->whereParam('commerce_orders.totalQty', $query->totalQty);
            }

            if (isset($query->totalWeight)) {
                $query->whereParam('commerce_orders.totalWeight', $query->totalWeight);
            }

            if (isset($query->totalDiscount)) {
                $query->whereParam('commerce_orders.totalDiscount', $query->totalDiscount);
            }

            if (isset($query->totalTax)) {
                $query->whereParam('commerce_orders.totalTax', $query->totalTax);
            }

            // Allow true but not null
            if (isset($query->isPaid) && $query->isPaid) {
                $query->whereColumn('commerce_orders.totalPaid', '>=', 'commerce_orders.totalPrice');
            }

            // Allow true but not null
            if (isset($query->isUnpaid) && $query->isUnpaid) {
                $query->whereColumn('commerce_orders.totalPaid', '<', 'commerce_orders.totalPrice');
            }

            // Allow integer/PurchasableInterface object or array of integers/PurchasableInterface objects
            if (isset($query->hasPurchasables)) {
                $purchasables = is_array($query->hasPurchasables) ? $query->hasPurchasables : [$query->hasPurchasables];
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

            if (isset($query->containsPurchasables)) {
                $purchasables = $query->containsPurchasables['purchasables'];
                $match = $query->containsPurchasables['match'];

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

            // Allow true or false but not null
            if (isset($query->hasTransactions)) {
                $method = $query->hasTransactions ? 'whereExists' : 'whereNotExists';
                $query->$method(function(Builder $sub) {
                    $sub->from(Table::TRANSACTIONS . ' as transactions')
                        ->whereColumn('transactions.orderId', 'elements.id');
                });
            }

            // Allow true or false but not null
            if (isset($query->hasLineItems)) {
                $method = $query->hasLineItems ? 'whereExists' : 'whereNotExists';
                $query->$method(function(Builder $sub) {
                    $sub->from(Table::LINEITEMS . ' as lineitems')
                        ->whereColumn('lineitems.orderId', 'elements.id');
                });
            }

            if (isset($query->hasAdminNotices)) {
                $method = $query->hasAdminNotices ? 'whereExists' : 'whereNotExists';
                $query->$method(function(Builder $sub) {
                    $sub->from(Table::ORDERNOTICES . ' as adminNotices')
                        ->whereColumn('adminNotices.orderId', 'elements.id')
                        ->where('adminNotices.noticeType', OrderNoticeType::Admin->value);
                });
            }
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

    public function withAll(bool $value = true): static
    {
        $this->withAll = $value;
        return $this;
    }

    public function withAddresses(bool $value = true): static
    {
        $this->withAddresses = $value;
        return $this;
    }

    public function withAdjustments(bool $value = true): static
    {
        $this->withAdjustments = $value;
        return $this;
    }

    public function withCustomer(bool $value = true): static
    {
        $this->withCustomer = $value;
        return $this;
    }

    public function withLineItems(bool $value = true): static
    {
        $this->withLineItems = $value;
        return $this;
    }

    public function withTransactions(bool $value = true): static
    {
        $this->withTransactions = $value;
        return $this;
    }

    /** @phpstan-ignore-next-line method.childParameterType, method.childReturnType (this query only ever hydrates Order elements; narrowing Collection<array-key, ElementInterface> to Collection<array-key, Order> is safe here even though the interface's Collection generics are invariant to PHPStan) */
    #[Override]
    public function afterHydrate(Collection $elements): Collection
    {
        if ($elements->isEmpty()) {
            return $elements;
        }

        /** @var Order[] $orders */
        $orders = $elements->all();

        if ($this->withLineItems || $this->withAll) {
            // TODO: migrate to app(LineItems::class)->eagerLoadLineItemsForOrders() once the LineItems
            // service and LineItem model are migrated to src/ (blocked on the Order/Purchasable-tied
            // LineItem migration - see laravel-migration-private.md)
            $orders = app(LineItems::class)->eagerLoadLineItemsForOrders($orders);
        }

        if ($this->withTransactions || $this->withAll) {
            $orders = app(Transactions::class)->eagerLoadTransactionsForOrders($orders);
        }

        if ($this->withAdjustments || $this->withAll) {
            $orders = app(OrderAdjustments::class)->eagerLoadOrderAdjustmentsForOrders($orders);
        }

        if ($this->withCustomer || $this->withAll) {
            $orders = app(Customers::class)->eagerLoadCustomerForOrders($orders);
        }

        if ($this->withAddresses || $this->withAll) {
            $orders = app(Orders::class)->eagerLoadAddressesForOrders($orders);
        }

        $orders = app(OrderNotices::class)->eagerLoadOrderNoticesForOrders($orders);

        return new Collection($orders);
    }
}
