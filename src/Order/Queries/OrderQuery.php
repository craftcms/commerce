<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Queries;

use CraftCms\Cms\Database\Table as CraftTable;
use CraftCms\Cms\Element\Queries\ElementQuery;
use CraftCms\Commerce\Customer\Customers;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\LineItem\LineItems;
use CraftCms\Commerce\Order\OrderAdjustments;
use CraftCms\Commerce\Order\OrderNotices;
use CraftCms\Commerce\Order\Orders;
use CraftCms\Commerce\Order\Queries\Concerns\QueriesOrderAttributes;
use CraftCms\Commerce\Payment\Transactions;
use Illuminate\Support\Collection;
use Override;
use Tpetry\QueryExpressions\Language\Alias;

/**
 * @extends ElementQuery<Order>
 */
class OrderQuery extends ElementQuery
{
    use QueriesOrderAttributes;

    protected string $table = Table::ORDERS;

    /** @var array<string, int> */
    protected array $defaultOrderBy = [
        'commerce_orders.id' => SORT_ASC,
    ];

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
