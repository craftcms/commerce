<?php

declare(strict_types=1);

use CraftCms\Cms\Address\Elements\Address;
use CraftCms\Cms\Database\Table as CmsTable;
use CraftCms\Cms\Element\Events\ElementSaved;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Str;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Customer\Customers;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Order\Edit\Exceptions\StaleOrderException;
use CraftCms\Commerce\Order\Edit\OrderEdit;
use CraftCms\Commerce\Order\Edit\OrderOperations;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\Events\AddLineItemEvent;
use CraftCms\Commerce\Store\Stores;
use CraftCms\Commerce\Tests\Support\OrdersFixture;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/** @param list<array<string, mixed>> $operations */
function previewCommitEdit(array $operations, ?DateTimeInterface $baseDateUpdated = null): OrderEdit
{
    return app(OrderOperations::class)->editFromArray([
        'version' => OrderEdit::VERSION,
        'baseDateUpdated' => $baseDateUpdated?->format(DateTimeInterface::ATOM),
        'operations' => $operations,
    ]);
}

/**
 * @return list<string> the insert, update and delete statements the callback runs
 */
function writeQueriesDuring(Closure $callback): array
{
    $writes = [];
    DB::listen(function(QueryExecuted $query) use (&$writes) {
        if (preg_match('/^\s*(insert|update|delete|replace)\b/i', $query->sql)) {
            $writes[] = $query->sql;
        }
    });

    $callback();

    return $writes;
}

function previewCommitCartWithNewCustomer(OrdersFixture $fixture): array
{
    $cart = new Order();
    $cart->number = bin2hex(random_bytes(16));
    $cart->storeId = $fixture->storeId;
    Elements::saveElement($cart, false);

    $customer = new User();
    $customer->username = 'customer2';
    $customer->email = 'customer2@crafttest.com';
    Elements::saveElement($customer);

    $home = new Address();
    $home->setPrimaryOwner($customer);
    $home->title = 'Home';
    $home->addressLine1 = '1 Customer Two Lane';
    $home->locality = 'Springfield';
    $home->administrativeArea = 'IL';
    $home->postalCode = '62701';
    $home->countryCode = 'US';
    Elements::saveElement($home);
    app(Customers::class)->savePrimaryBillingAddressId($customer, $home->id);
    app(Customers::class)->savePrimaryShippingAddressId($customer, $home->id);

    $store = app(Stores::class)->getStoreById($fixture->storeId);
    $store->setAutoSetNewCartAddresses(true);
    app(Stores::class)->saveStore($store);

    return [Order::find()->id($cart->id)->one(), $customer];
}

describe('previewing', function() {
    it('shows a cart’s new customer, addresses, line items and totals without writing to the database', function() {
        $fixture = OrdersFixture::seed();
        [$cart, $customer] = previewCommitCartWithNewCustomer($fixture);

        $writes = writeQueriesDuring(fn() => app(OrderOperations::class)->preview($cart, previewCommitEdit([
            ['type' => 'setCustomer', 'customerId' => $customer->id],
            ['type' => 'addLineItem', 'uid' => (string)Str::uuid(), 'purchasableId' => $fixture->white->id, 'qty' => 2],
            ['type' => 'setShippingMethod', 'shippingMethodHandle' => 'usShipping'],
        ])));

        expect($writes)->toBe([]);
        expect($cart->getCustomerId())->toBe($customer->id);
        expect($cart->getShippingAddress()->addressLine1)->toBe('1 Customer Two Lane');
        expect($cart->getBillingAddress()->addressLine1)->toBe('1 Customer Two Lane');
        expect($cart->getItemSubtotal())->toBe(round($fixture->white->getPrice() * 2, 2));

        $saved = Order::find()->id($cart->id)->one();
        expect($saved->getCustomerId())->toBeNull();
        expect($saved->getLineItems())->toBeEmpty();
    });

    it('shows a completed order’s manual changes without writing to the database', function() {
        $fixture = OrdersFixture::seed();
        $order = $fixture->orders['completed-new'];
        $lineItem = $order->getLineItems()[0];
        $home = new Address();
        $home->setPrimaryOwner($fixture->customer);
        $home->title = 'Home';
        $home->addressLine1 = '23 Woodworth';
        $home->countryCode = 'US';
        Elements::saveElement($home);

        $writes = writeQueriesDuring(fn() => app(OrderOperations::class)->preview($order, previewCommitEdit([
            ['type' => 'updateLineItem', 'lineItemUid' => $lineItem->uid, 'changes' => ['price' => 5, 'qty' => 3]],
            ['type' => 'addAdjustment', 'adjustmentType' => 'discount', 'name' => 'Goodwill', 'amount' => -5],
            ['type' => 'setAddress', 'addressType' => 'shipping', 'sourceAddressId' => $home->id],
            ['type' => 'copyAddress', 'from' => 'shipping'],
            ['type' => 'recalculate'],
        ])));

        expect($writes)->toBe([]);
        expect($order->getBillingAddress()->addressLine1)->toBe('23 Woodworth');
    });

    it('rolls back anything else that writes to the database during a preview', function() {
        $fixture = OrdersFixture::seed();
        $order = $fixture->orders['completed-new'];
        $noticeCount = DB::table(Table::ORDERNOTICES)->count();

        Event::listen(AddLineItemEvent::class, fn() => DB::table(Table::ORDERNOTICES)->insert([
            'orderId' => $order->id,
            'type' => 'note',
            'attribute' => 'lineItems',
            'message' => 'Written by a listener',
            'dateCreated' => now(),
            'dateUpdated' => now(),
        ]));

        app(OrderOperations::class)->preview($order, previewCommitEdit([
            ['type' => 'addLineItem', 'uid' => (string)Str::uuid(), 'purchasableId' => $fixture->white->id],
        ]));

        expect(DB::table(Table::ORDERNOTICES)->count())->toBe($noticeCount);
    });
});

describe('committing', function() {
    it('saves a cart’s edit once', function() {
        $fixture = OrdersFixture::seed();
        [$cart, $customer] = previewCommitCartWithNewCustomer($fixture);
        $uid = (string)Str::uuid();

        $saves = 0;
        Event::listen(ElementSaved::class, function(ElementSaved $event) use (&$saves, $cart) {
            if ($event->element instanceof Order && $event->element->id === $cart->id) {
                $saves++;
            }
        });

        $committed = app(OrderOperations::class)->commit($cart, previewCommitEdit([
            ['type' => 'setCustomer', 'customerId' => $customer->id],
            ['type' => 'addLineItem', 'uid' => $uid, 'purchasableId' => $fixture->white->id, 'qty' => 2],
        ], $cart->dateUpdated));

        expect($committed)->toBeTrue();
        expect($saves)->toBe(1);

        $saved = Order::find()->id($cart->id)->one();

        expect($saved->getCustomerId())->toBe($customer->id);
        expect($saved->getShippingAddress()->addressLine1)->toBe('1 Customer Two Lane');
        expect($saved->getShippingAddress()->getOwnerId())->toBe($cart->id);
        expect($saved->sourceShippingAddressId)->toBe($customer->getPrimaryShippingAddress()->id);
        expect(collect($saved->getLineItems())->firstWhere('uid', $uid)?->qty)->toBe(2);
        expect($saved->getRecalculationMode())->toBe(Order::RECALCULATION_MODE_ALL);
    });

    it('saves a completed order’s edit and keeps its manual changes', function() {
        $order = OrdersFixture::seed()->orders['completed-new'];
        $lineItem = $order->getLineItems()[0];

        $committed = app(OrderOperations::class)->commit($order, previewCommitEdit([
            ['type' => 'updateLineItem', 'lineItemUid' => $lineItem->uid, 'changes' => ['price' => 5]],
            ['type' => 'addAdjustment', 'adjustmentType' => 'discount', 'name' => 'Goodwill', 'amount' => -5],
        ], $order->dateUpdated));

        expect($committed)->toBeTrue();

        $saved = Order::find()->id($order->id)->one();

        expect(collect($saved->getLineItems())->firstWhere('uid', $lineItem->uid)->getPrice())->toBe(5.0);
        expect(collect($saved->getAdjustments())->pluck('name')->all())->toContain('Goodwill');
        expect($saved->getRecalculationMode())->toBe(Order::RECALCULATION_MODE_NONE);
    });

    it('rejects an edit made against an earlier version of the order', function() {
        $order = OrdersFixture::seed()->orders['completed-new'];

        app(OrderOperations::class)->commit($order, previewCommitEdit([
            ['type' => 'setReference', 'reference' => 'TOO-LATE'],
        ], (clone $order->dateUpdated)->modify('-1 hour')));
    })->throws(StaleOrderException::class);

    it('rejects an edit to an order that changed after it was loaded', function() {
        $order = OrdersFixture::seed()->orders['completed-new'];
        DB::table(CmsTable::ELEMENTS)->where('id', $order->id)->update(['dateUpdated' => '2030-01-01 00:00:00']);

        try {
            app(OrderOperations::class)->commit($order, previewCommitEdit([
                ['type' => 'setReference', 'reference' => 'TOO-LATE'],
            ]));
        } catch (StaleOrderException) {
            expect(Order::find()->id($order->id)->one()->reference)->toBe($order->reference);

            return;
        }

        test()->fail('Expected the edit to be rejected.');
    });

    it('does not save an edit that leaves the order invalid', function() {
        $order = OrdersFixture::seed()->orders['completed-new'];
        $shippingAddressLine1 = $order->getShippingAddress()->addressLine1;

        $committed = app(OrderOperations::class)->commit($order, previewCommitEdit([
            ['type' => 'setAddress', 'addressType' => 'shipping', 'address' => ['countryCode' => 'US', 'administrativeArea' => 'not-a-state']],
        ], $order->dateUpdated));

        expect($committed)->toBeFalse();
        expect($order->hasErrors())->toBeTrue();
        expect(Order::find()->id($order->id)->one()->getShippingAddress()->addressLine1)->toBe($shippingAddressLine1);
    });
});
