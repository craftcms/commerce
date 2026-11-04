<?php

declare(strict_types=1);

use CraftCms\Cms\Address\Elements\Address;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\Support\Str;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Customer\Customers;
use CraftCms\Commerce\Order\Data\LineItemStatus;
use CraftCms\Commerce\Order\Data\OrderNotice;
use CraftCms\Commerce\Order\Edit\Exceptions\OperationNotApplicableException;
use CraftCms\Commerce\Order\Edit\OrderEdit;
use CraftCms\Commerce\Order\Edit\OrderOperations;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\Enums\OrderNoticeType;
use CraftCms\Commerce\Order\Events\LineItemAdding;
use CraftCms\Commerce\Order\LineItem\Enums\LineItemType;
use CraftCms\Commerce\Order\LineItem\LineItems;
use CraftCms\Commerce\Order\LineItemStatuses;
use CraftCms\Commerce\Purchasable\Contracts\PurchasableInterface;
use CraftCms\Commerce\Purchasable\Events\PurchasableAvailabilityResolving;
use CraftCms\Commerce\Purchasable\Purchasables;
use CraftCms\Commerce\Shipping\ShippingCategories;
use CraftCms\Commerce\Store\Stores;
use CraftCms\Commerce\Tax\TaxCategories;
use CraftCms\Commerce\Tests\Support\OrdersFixture;
use CraftCms\Commerce\Tests\Support\StoresFixture;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

/**
 * Holds the order operations to the behaviour today's order edit screen has when it applies a
 * draft (see tests/Feature/Http/Controllers/OrderEditSaveTest.php).
 */

/** @param list<array<string, mixed>> $operations */
function orderEditFromArray(array $operations): OrderEdit
{
    return app(OrderOperations::class)->editFromArray([
        'version' => OrderEdit::VERSION,
        'operations' => $operations,
    ]);
}

/** @param list<array<string, mixed>> $operations */
function applyOrderOperations(Order $order, array $operations): Order
{
    app(OrderOperations::class)->apply($order, orderEditFromArray($operations)->operations);

    return $order;
}

/** @param list<array<string, mixed>> $operations */
function commitOrderOperations(Order $order, array $operations): Order
{
    applyOrderOperations($order, $operations);

    if (!Elements::saveElement($order)) {
        throw new RuntimeException('Could not save order: ' . json_encode($order->errors()->all()));
    }

    return Order::find()->id($order->id)->one();
}

function orderOperationsCart(OrdersFixture $fixture): Order
{
    $cart = new Order();
    $cart->number = bin2hex(random_bytes(16));
    $cart->storeId = $fixture->storeId;
    $cart->setCustomerId($fixture->customer->id);
    Elements::saveElement($cart, false);

    $cart->setLineItems([
        app(LineItems::class)->create($cart, ['purchasableId' => $fixture->white->id, 'qty' => 1]),
    ]);
    Elements::saveElement($cart, false);

    return Order::find()->id($cart->id)->one();
}

function orderOperationsCustomerAddress(User $customer, string $line1 = '23 Woodworth'): Address
{
    $address = new Address();
    $address->setPrimaryOwner($customer);
    $address->title = 'Home';
    $address->addressLine1 = $line1;
    $address->locality = 'County Island';
    $address->administrativeArea = 'NY';
    $address->postalCode = '12345';
    $address->countryCode = 'US';
    Elements::saveElement($address);

    return $address;
}

function expectNotApplicable(Closure $callback, int $operationIndex): void
{
    try {
        $callback();
    } catch (OperationNotApplicableException $exception) {
        expect($exception->operationIndex)->toBe($operationIndex);

        return;
    }

    test()->fail('Expected the operation not to be applicable.');
}

describe('parsing', function() {
    it('round-trips an edit through its stored shape', function() {
        $edit = orderEditFromArray([
            ['type' => 'addLineItem', 'uid' => (string)Str::uuid(), 'purchasableId' => 1, 'qty' => 2],
            ['type' => 'updateLineItem', 'lineItemUid' => 'abc', 'changes' => ['qty' => 3, 'promotionalPrice' => null]],
            ['type' => 'setAddress', 'addressType' => 'shipping', 'sourceAddressId' => 5],
            ['type' => 'setDateOrdered', 'dateOrdered' => '2026-01-15T09:30:00+00:00'],
            ['type' => 'recalculate'],
        ]);

        $stored = json_decode(json_encode($edit), true);

        expect($stored['version'])->toBe(OrderEdit::VERSION);
        expect(app(OrderOperations::class)->editFromArray($stored))->toEqual($edit);
    });

    it('keeps only the line item changes it knows about', function() {
        $edit = orderEditFromArray([
            ['type' => 'updateLineItem', 'lineItemUid' => 'abc', 'changes' => ['qty' => '3', 'orderId' => 99]],
        ]);

        expect($edit->operations[0]->changes)->toBe(['qty' => 3]);
    });

    it('reports invalid operations against their position in the edit', function(array $operation, string $errorKey) {
        try {
            orderEditFromArray([['type' => 'recalculate'], $operation]);
        } catch (ValidationException $exception) {
            expect($exception->errors())->toHaveKey($errorKey);

            return;
        }

        test()->fail('Expected a validation exception.');
    })->with([
        'unknown type' => [['type' => 'teleportOrder'], 'operations.1.type'],
        'missing data' => [['type' => 'addLineItem', 'uid' => (string)Str::uuid()], 'operations.1.purchasableId'],
        'invalid quantity' => [['type' => 'updateLineItem', 'lineItemUid' => 'abc', 'changes' => ['qty' => 0]], 'operations.1.changes.qty'],
        'invalid adjustment type' => [['type' => 'addAdjustment', 'adjustmentType' => 'magic', 'name' => 'x', 'amount' => 1], 'operations.1.adjustmentType'],
    ]);

    it('rejects edits stored by a different version', function() {
        app(OrderOperations::class)->editFromArray(['version' => OrderEdit::VERSION + 1, 'operations' => []]);
    })->throws(ValidationException::class);
});

describe('applying', function() {
    it('changes the order in memory without saving it', function() {
        $order = OrdersFixture::seed()->orders['completed-new'];
        $lineItem = $order->getLineItems()[0];
        $savedQty = $lineItem->qty;

        applyOrderOperations($order, [
            ['type' => 'updateLineItem', 'lineItemUid' => $lineItem->uid, 'changes' => ['qty' => 7]],
        ]);

        expect($order->getLineItems()[0]->qty)->toBe(7);
        expect(Order::find()->id($order->id)->one()->getLineItems()[0]->qty)->toBe($savedQty);
    });

    it('recalculates a cart after applying operations to it', function() {
        $fixture = OrdersFixture::seed();
        $cart = orderOperationsCart($fixture);

        applyOrderOperations($cart, [
            ['type' => 'updateLineItem', 'lineItemUid' => $cart->getLineItems()[0]->uid, 'changes' => ['qty' => 3]],
        ]);

        expect($cart->getItemSubtotal())->toBe(round($fixture->white->getPrice() * 3, 2));
    });

    it('lets later operations refer to a line item added earlier in the same edit', function() {
        $fixture = OrdersFixture::seed();
        $order = $fixture->orders['completed-new-past'];
        $uid = (string)Str::uuid();

        $saved = commitOrderOperations($order, [
            ['type' => 'addLineItem', 'uid' => $uid, 'purchasableId' => $fixture->white->id, 'qty' => 1],
            ['type' => 'updateLineItem', 'lineItemUid' => $uid, 'changes' => ['qty' => 4]],
            ['type' => 'setLineItemNotes', 'lineItemUid' => $uid, 'note' => 'Gift', 'privateNote' => 'VIP'],
        ]);

        $added = collect($saved->getLineItems())->firstWhere('uid', $uid);

        expect($added->qty)->toBe(4);
        expect($added->note)->toBe('Gift');
        expect($added->privateNote)->toBe('VIP');
    });

    it('reports which operation no longer applies', function() {
        $order = OrdersFixture::seed()->orders['completed-new'];

        expectNotApplicable(fn() => applyOrderOperations($order, [
            ['type' => 'setReference', 'reference' => 'A'],
            ['type' => 'removeLineItem', 'lineItemUid' => 'not-a-line-item'],
        ]), 1);
    });
});

function recordPurchasableCustomers(): object
{
    $recorder = new class() extends Purchasables {
        /** @var list<int|false|null> */
        public array $customers = [];

        public function getPurchasableById(int $purchasableId, ?int $siteId = null, int|false|null $forCustomer = null): ?PurchasableInterface
        {
            $this->customers[] = $forCustomer;

            return parent::getPurchasableById($purchasableId, $siteId, $forCustomer);
        }
    };

    app()->instance(Purchasables::class, $recorder);

    return $recorder;
}

describe('line items', function() {
    it('prices a purchasable for a guest when the order has no customer', function() {
        $fixture = OrdersFixture::seed();
        $recorder = recordPurchasableCustomers();

        $cart = new Order();
        $cart->number = bin2hex(random_bytes(16));
        $cart->storeId = $fixture->storeId;
        Elements::saveElement($cart, false);

        expect($cart->getPricingCustomerId())->toBeFalse();

        applyOrderOperations(Order::find()->id($cart->id)->one(), [
            ['type' => 'addLineItem', 'uid' => (string)Str::uuid(), 'purchasableId' => $fixture->white->id],
        ]);

        expect($recorder->customers)->not->toBeEmpty()->each->toBeFalse();
    });

    it('prices a purchasable for the order’s customer', function() {
        $fixture = OrdersFixture::seed();
        $recorder = recordPurchasableCustomers();
        $order = $fixture->orders['completed-new'];

        expect($order->getPricingCustomerId())->toBe($fixture->customer->id);

        applyOrderOperations($order, [
            ['type' => 'addLineItem', 'uid' => (string)Str::uuid(), 'purchasableId' => $fixture->white->id],
        ]);

        expect($recorder->customers)->not->toBeEmpty()->each->toBe($fixture->customer->id);
    });

    it('rejects a purchasable that is not available to the order', function() {
        $fixture = OrdersFixture::seed();
        $order = $fixture->orders['completed-new'];
        $count = count($order->getLineItems());

        Event::listen(PurchasableAvailabilityResolving::class, fn(PurchasableAvailabilityResolving $event) => $event->isAvailable = false);

        expectNotApplicable(fn() => applyOrderOperations($order, [
            ['type' => 'addLineItem', 'uid' => (string)Str::uuid(), 'purchasableId' => $fixture->white->id],
        ]), 0);

        expect($order->getLineItems())->toHaveCount($count);
    });

    it('rejects a line item that a listener refuses to add', function() {
        $fixture = OrdersFixture::seed();
        $order = $fixture->orders['completed-new'];

        Event::listen(LineItemAdding::class, fn(LineItemAdding $event) => $event->isValid = false);

        expectNotApplicable(fn() => applyOrderOperations($order, [
            ['type' => 'addLineItem', 'uid' => (string)Str::uuid(), 'purchasableId' => $fixture->white->id],
        ]), 0);
    });

    it('keeps a manual price on a completed order', function() {
        $order = OrdersFixture::seed()->orders['completed-new'];
        $lineItem = $order->getLineItems()[0];

        $saved = commitOrderOperations($order, [
            ['type' => 'updateLineItem', 'lineItemUid' => $lineItem->uid, 'changes' => ['price' => '5', 'promotionalPrice' => '4']],
        ]);

        $savedLineItem = collect($saved->getLineItems())->firstWhere('uid', $lineItem->uid);

        expect($savedLineItem->getPrice())->toBe(5.0);
        expect($savedLineItem->getPromotionalPrice())->toBe(4.0);
        expect($saved->getRecalculationMode())->toBe(Order::RECALCULATION_MODE_NONE);
    });

    it('does not override a purchasable’s price on a cart', function() {
        $cart = orderOperationsCart(OrdersFixture::seed());

        expectNotApplicable(fn() => applyOrderOperations($cart, [
            ['type' => 'updateLineItem', 'lineItemUid' => $cart->getLineItems()[0]->uid, 'changes' => ['price' => '5']],
        ]), 0);
    });

    it('only changes the description, SKU and flags of custom line items', function() {
        $order = OrdersFixture::seed()->orders['completed-new'];

        expectNotApplicable(fn() => applyOrderOperations($order, [
            ['type' => 'updateLineItem', 'lineItemUid' => $order->getLineItems()[0]->uid, 'changes' => ['sku' => 'NEW']],
        ]), 0);
    });

    it('adds and updates a custom line item', function() {
        $order = OrdersFixture::seed()->orders['completed-new'];
        $taxCategoryId = app(TaxCategories::class)->getDefaultTaxCategory()->id;
        $shippingCategoryId = app(ShippingCategories::class)->getDefaultShippingCategory($order->storeId)->id;
        $uid = (string)Str::uuid();

        $saved = commitOrderOperations($order, [
            [
                'type' => 'addCustomLineItem',
                'uid' => $uid,
                'description' => 'Gift wrapping',
                'sku' => 'GIFT-WRAP',
                'price' => '3.50',
                'qty' => 2,
                'taxCategoryId' => $taxCategoryId,
                'shippingCategoryId' => $shippingCategoryId,
                'hasFreeShipping' => true,
                'isPromotable' => false,
                'isShippable' => false,
                'isTaxable' => false,
            ],
            ['type' => 'updateLineItem', 'lineItemUid' => $uid, 'changes' => ['description' => 'Premium gift wrapping']],
        ]);

        $custom = collect($saved->getLineItems())->firstWhere('uid', $uid);

        expect($custom->type)->toBe(LineItemType::Custom);
        expect($custom->getDescription())->toBe('Premium gift wrapping');
        expect($custom->getSku())->toBe('GIFT-WRAP');
        expect($custom->getPrice())->toBe(3.5);
        expect($custom->qty)->toBe(2);
        expect($custom->taxCategoryId)->toBe($taxCategoryId);
        expect($custom->shippingCategoryId)->toBe($shippingCategoryId);
        expect($custom->getHasFreeShipping())->toBeTrue();
        expect($custom->getIsPromotable())->toBeFalse();
        expect($custom->getIsShippable())->toBeFalse();
        expect($custom->getIsTaxable())->toBeFalse();
    });

    it('removes a line item', function() {
        $order = OrdersFixture::seed()->orders['completed-new'];
        [$white, $blue] = $order->getLineItems();

        $saved = commitOrderOperations($order, [
            ['type' => 'removeLineItem', 'lineItemUid' => $white->uid],
        ]);

        expect(collect($saved->getLineItems())->pluck('id')->all())->toBe([$blue->id]);
    });

    it('sets a line item’s status and options', function() {
        $fixture = OrdersFixture::seed();
        $order = $fixture->orders['completed-new'];
        $lineItem = $order->getLineItems()[0];

        $status = new LineItemStatus();
        $status->storeId = $fixture->storeId;
        $status->name = 'Packed';
        $status->handle = 'packed';
        app(LineItemStatuses::class)->saveLineItemStatus($status);

        $saved = commitOrderOperations($order, [
            ['type' => 'setLineItemStatus', 'lineItemUid' => $lineItem->uid, 'lineItemStatusId' => $status->id],
            ['type' => 'setLineItemOptions', 'lineItemUid' => $lineItem->uid, 'options' => ['engraving' => 'Happy birthday']],
        ]);

        $savedLineItem = collect($saved->getLineItems())->firstWhere('uid', $lineItem->uid);

        expect($savedLineItem->lineItemStatusId)->toBe($status->id);
        expect($savedLineItem->getOptions())->toBe(['engraving' => 'Happy birthday']);
    });
});

describe('adjustments', function() {
    it('adds manual adjustments to a completed order and its line items', function() {
        $order = OrdersFixture::seed()->orders['completed-new'];
        $lineItem = $order->getLineItems()[0];

        $saved = commitOrderOperations($order, [
            ['type' => 'addAdjustment', 'adjustmentType' => 'discount', 'name' => 'Goodwill', 'amount' => -5],
            ['type' => 'addAdjustment', 'adjustmentType' => 'discount', 'name' => 'Line discount', 'amount' => -1, 'lineItemUid' => $lineItem->uid],
        ]);

        $adjustments = collect($saved->getAdjustments());

        expect($adjustments->firstWhere('name', 'Goodwill')->amount)->toBe(-5.0);
        expect($adjustments->firstWhere('name', 'Goodwill')->getLineItem())->toBeNull();
        expect($adjustments->firstWhere('name', 'Line discount')->getLineItem()?->id)->toBe($lineItem->id);
    });

    it('updates and removes saved adjustments', function() {
        $order = OrdersFixture::seed()->orders['completed-new'];
        $order = commitOrderOperations($order, [
            ['type' => 'addAdjustment', 'adjustmentType' => 'discount', 'name' => 'Goodwill', 'amount' => -5],
            ['type' => 'addAdjustment', 'adjustmentType' => 'shipping', 'name' => 'Courier', 'amount' => 10],
        ]);
        $adjustments = collect($order->getAdjustments());

        $saved = commitOrderOperations($order, [
            ['type' => 'updateAdjustment', 'adjustmentId' => $adjustments->firstWhere('name', 'Goodwill')->id, 'changes' => ['amount' => -7, 'name' => 'Apology']],
            ['type' => 'removeAdjustment', 'adjustmentId' => $adjustments->firstWhere('name', 'Courier')->id],
        ]);

        $names = collect($saved->getAdjustments())->pluck('amount', 'name')->all();

        expect($names)->toHaveKey('Apology', -7.0);
        expect($names)->not->toHaveKey('Courier');
        expect($names)->not->toHaveKey('Goodwill');
    });

    it('does not add manual adjustments to a cart', function() {
        $cart = orderOperationsCart(OrdersFixture::seed());

        expectNotApplicable(fn() => applyOrderOperations($cart, [
            ['type' => 'addAdjustment', 'adjustmentType' => 'discount', 'name' => 'Goodwill', 'amount' => -5],
        ]), 0);
    });

    it('replaces manual prices and adjustments when the edit recalculates the order', function() {
        $order = OrdersFixture::seed()->orders['completed-new'];
        $lineItem = $order->getLineItems()[0];

        $saved = commitOrderOperations($order, [
            ['type' => 'updateLineItem', 'lineItemUid' => $lineItem->uid, 'changes' => ['price' => '5']],
            ['type' => 'addAdjustment', 'adjustmentType' => 'discount', 'name' => 'Goodwill', 'amount' => -5],
            ['type' => 'recalculate'],
        ]);

        $savedLineItem = collect($saved->getLineItems())->firstWhere('uid', $lineItem->uid);

        expect($savedLineItem->getPrice())->toBe($lineItem->getPurchasable()->getPrice());
        expect(collect($saved->getAdjustments())->pluck('name')->all())->not->toContain('Goodwill');
        expect($saved->getRecalculationMode())->toBe(Order::RECALCULATION_MODE_NONE);
    });
});

describe('customer and addresses', function() {
    it('copies one of the customer’s addresses onto the order without saving anything', function() {
        $fixture = OrdersFixture::seed();
        $order = $fixture->orders['completed-new'];
        $source = orderOperationsCustomerAddress($fixture->customer);
        $addressCount = Address::find()->count();

        applyOrderOperations($order, [
            ['type' => 'setAddress', 'addressType' => 'shipping', 'sourceAddressId' => $source->id],
        ]);

        expect($order->getShippingAddress()->addressLine1)->toBe('23 Woodworth');
        expect($order->sourceShippingAddressId)->toBe($source->id);
        expect(Address::find()->count())->toBe($addressCount);

        expect(Elements::saveElement($order))->toBeTrue();
        $saved = Order::find()->id($order->id)->one();

        expect($saved->getShippingAddress()->addressLine1)->toBe('23 Woodworth');
        expect($saved->getShippingAddress()->id)->not->toBe($source->id);
        expect($saved->getShippingAddress()->getOwnerId())->toBe($order->id);
    });

    it('sets an order address from attributes', function() {
        $order = OrdersFixture::seed()->orders['completed-new'];

        $saved = commitOrderOperations($order, [
            ['type' => 'setAddress', 'addressType' => 'billing', 'address' => [
                'firstName' => 'Marty',
                'lastName' => 'McFly',
                'addressLine1' => '9303 Lyon Drive',
                'locality' => 'Hill Valley',
                'administrativeArea' => 'CA',
                'postalCode' => '95420',
                'countryCode' => 'US',
            ]],
        ]);

        expect($saved->getBillingAddress()->addressLine1)->toBe('9303 Lyon Drive');
        expect($saved->getBillingAddress()->addressLine2)->toBeNull();
        expect($saved->sourceBillingAddressId)->toBeNull();
    });

    it('does not copy an address that isn’t the customer’s', function() {
        $fixture = OrdersFixture::seed();
        $stranger = new User();
        $stranger->username = 'stranger';
        $stranger->email = 'stranger@crafttest.com';
        Elements::saveElement($stranger);

        expectNotApplicable(fn() => applyOrderOperations($fixture->orders['completed-new'], [
            ['type' => 'setAddress', 'addressType' => 'shipping', 'sourceAddressId' => orderOperationsCustomerAddress($stranger)->id],
        ]), 0);
    });

    it('copies the shipping address to the billing address', function() {
        $order = OrdersFixture::seed()->orders['completed-shipped'];

        $saved = commitOrderOperations($order, [
            ['type' => 'copyAddress', 'from' => 'shipping'],
        ]);

        expect($saved->getBillingAddress()->addressLine1)->toBe($order->getShippingAddress()->addressLine1);
        expect($saved->getBillingAddress()->id)->not->toBe($saved->getShippingAddress()->id);
    });

    it('removes an order address', function() {
        $order = OrdersFixture::seed()->orders['completed-new'];

        $saved = commitOrderOperations($order, [
            ['type' => 'removeAddress', 'addressType' => 'billing'],
        ]);

        expect($saved->getBillingAddress())->toBeNull();
    });

    it('gives a cart its new customer’s primary addresses', function() {
        $fixture = OrdersFixture::seed();
        $cart = orderOperationsCart($fixture);

        $customer = new User();
        $customer->username = 'customer2';
        $customer->email = 'customer2@crafttest.com';
        Elements::saveElement($customer);
        $home = orderOperationsCustomerAddress($customer, '1 Customer Two Lane');
        app(Customers::class)->savePrimaryBillingAddressId($customer, $home->id);
        app(Customers::class)->savePrimaryShippingAddressId($customer, $home->id);

        $store = app(Stores::class)->getStoreById($fixture->storeId);
        $store->setAutoSetNewCartAddresses(true);
        app(Stores::class)->saveStore($store);

        applyOrderOperations($cart, [
            ['type' => 'setCustomer', 'customerId' => $customer->id],
        ]);

        expect($cart->getCustomerId())->toBe($customer->id);
        expect($cart->getShippingAddress()->addressLine1)->toBe('1 Customer Two Lane');
        expect($cart->getBillingAddress()->addressLine1)->toBe('1 Customer Two Lane');
    });

    it('does not set a customer that no longer exists', function() {
        expectNotApplicable(fn() => applyOrderOperations(OrdersFixture::seed()->orders['completed-new'], [
            ['type' => 'setCustomer', 'customerId' => 999999],
        ]), 0);
    });
});

describe('order details', function() {
    it('sets the reference, coupon code, shipping method and order site', function() {
        $order = OrdersFixture::seed()->orders['completed-new'];
        $site = Sites::getPrimarySite();

        $saved = commitOrderOperations($order, [
            ['type' => 'setReference', 'reference' => 'PHONE-42'],
            ['type' => 'setCouponCode', 'couponCode' => 'SUMMER'],
            ['type' => 'setShippingMethod', 'shippingMethodHandle' => 'usShipping'],
            ['type' => 'setOrderSite', 'orderSiteId' => $site->id],
        ]);

        expect($saved->reference)->toBe('PHONE-42');
        expect($saved->couponCode)->toBe('SUMMER');
        expect($saved->shippingMethodHandle)->toBe('usShipping');
        expect($saved->shippingMethodName)->toBe('US Shipping');
        expect($saved->orderSiteId)->toBe($site->id);
        expect($saved->orderLanguage)->toBe($site->language);
    });

    it('does not set an order site from another store', function() {
        $order = OrdersFixture::seed()->orders['completed-new'];
        $euSite = StoresFixture::seed()->euSite;

        expectNotApplicable(fn() => applyOrderOperations($order, [
            ['type' => 'setOrderSite', 'orderSiteId' => $euSite->id],
        ]), 0);
    });

    it('removes the shipping method', function() {
        $order = OrdersFixture::seed()->orders['completed-new'];

        $saved = commitOrderOperations($order, [
            ['type' => 'setShippingMethod', 'shippingMethodHandle' => null],
        ]);

        expect($saved->shippingMethodHandle)->toBeNull();
        expect($saved->shippingMethodName)->toBeNull();
    });

    it('changes when a completed order was placed', function() {
        $order = OrdersFixture::seed()->orders['completed-new'];

        $saved = commitOrderOperations($order, [
            ['type' => 'setDateOrdered', 'dateOrdered' => '2026-01-15T09:30:00+00:00'],
        ]);

        expect($saved->dateOrdered->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i'))->toBe('2026-01-15 09:30');
    });

    it('does not set a date ordered on a cart', function() {
        expectNotApplicable(fn() => applyOrderOperations(orderOperationsCart(OrdersFixture::seed()), [
            ['type' => 'setDateOrdered', 'dateOrdered' => '2026-01-15T09:30:00+00:00'],
        ]), 0);
    });

    it('clears all of one type of notice, or just the given ones', function() {
        $order = OrdersFixture::seed()->orders['completed-new'];
        $notice = fn(OrderNoticeType $noticeType, string $message) => new OrderNotice([
            'type' => 'note',
            'attribute' => 'lineItems',
            'message' => $message,
            'noticeType' => $noticeType,
        ]);
        $order->addNotices([
            $notice(OrderNoticeType::Customer, 'Price changed'),
            $notice(OrderNoticeType::Customer, 'Item removed'),
            $notice(OrderNoticeType::Admin, 'Check stock'),
            $notice(OrderNoticeType::Admin, 'Call customer'),
        ]);
        Elements::saveElement($order);
        $order = Order::find()->id($order->id)->one();
        $checkStock = collect($order->getAdminNotices())->firstWhere('message', 'Check stock');

        $saved = commitOrderOperations($order, [
            ['type' => 'clearNotices', 'noticeType' => 'customer'],
            ['type' => 'clearNotices', 'noticeType' => 'admin', 'noticeIds' => [$checkStock->id]],
        ]);

        expect($saved->getNotices())->toBeEmpty();
        expect(collect($saved->getAdminNotices())->pluck('message')->all())->toBe(['Call customer']);
    });
});
