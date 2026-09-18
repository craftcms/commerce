<?php

declare(strict_types=1);

use CraftCms\Cms\Address\Addresses;
use CraftCms\Cms\Address\Elements\Address;
use CraftCms\Cms\Database\Table as CraftTable;
use CraftCms\Cms\Field\Fields;
use CraftCms\Cms\Field\PlainText;
use CraftCms\Cms\FieldLayout\FieldLayout;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\Validation\Events\ValidationRulesResolving;
use CraftCms\Commerce\Store\Stores;
use CraftCms\Commerce\Tests\Support\CartsFixture;
use CraftCms\Commerce\Tests\Support\SalesFixture;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\postJson;

beforeEach(function() {
    prioritizeCommerceRoutes();
});

it('returns a new, empty cart as a full JSON representation', function() {
    $response = get(Url::actionUrl('commerce/cart/get-cart'), ['Accept' => 'application/json'])
        ->assertOk();

    $cart = $response->json('cart');

    expect($cart['total'])->toEqual(0);

    // Assert types
    expect($cart['number'])->toBeString();
    expect($cart['reference'])->toBeNull();
    expect($cart['couponCode'])->toBeNull();
    expect($cart['isCompleted'])->toBeBool();
    expect($cart['dateOrdered'])->toBeNull();
    expect($cart['datePaid'])->toBeNull();
    expect($cart['dateAuthorized'])->toBeNull();
    expect($cart['currency'])->toBeString();
    expect($cart['gatewayId'])->toBeNull();
    expect($cart['lastIp'])->toBeString();
    expect($cart['message'])->toBeNull();
    expect($cart['returnUrl'])->toBeNull();
    expect($cart['cancelUrl'])->toBeNull();
    expect($cart['orderStatusId'])->toBeNull();
    expect($cart['orderLanguage'])->toBeString();
    expect($cart['orderSiteId'])->toBeInt();
    expect($cart['origin'])->toBeString();
    expect($cart['billingAddressId'])->toBeNull();
    expect($cart['shippingAddressId'])->toBeNull();
    expect($cart['makePrimaryShippingAddress'])->toBeBool();
    expect($cart['makePrimaryBillingAddress'])->toBeBool();
    expect($cart['shippingSameAsBilling'])->toBeBool();
    expect($cart['billingSameAsShipping'])->toBeBool();
    expect($cart['estimatedBillingAddressId'])->toBeNull();
    expect($cart['estimatedShippingAddressId'])->toBeNull();
    expect($cart['estimatedBillingSameAsShipping'])->toBeBool();
    expect($cart['shippingMethodHandle'])->toBeString();
    expect($cart['shippingMethodName'])->toBeNull();
    expect($cart['customerId'])->toBeNull();
    expect($cart['registerUserOnOrderComplete'])->toBeBool();
    expect($cart['paymentSourceId'])->toBeNull();
    expect($cart['storedTotalPrice'])->toBeNull();
    expect($cart['storedTotalPaid'])->toBeNull();
    expect($cart['storedItemTotal'])->toBeNull();
    expect($cart['storedItemSubtotal'])->toBeNull();
    expect($cart['storedTotalShippingCost'])->toBeNull();
    expect($cart['storedTotalDiscount'])->toBeNull();
    expect($cart['storedTotalTax'])->toBeNull();
    expect($cart['storedTotalTaxIncluded'])->toBeNull();
    expect($cart['id'])->toBeNull();
    expect($cart['enabled'])->toBeBool();
    expect($cart['siteId'])->toBeInt();
    expect($cart['status'])->toBeString();
    // Zero-valued monetary/numeric fields round-trip through JSON as PHP integers rather than
    // floats (PHP's JSON encoder drops the trailing `.0` from a whole-number float, and
    // `TestResponse::json()` decodes it back as an int) - `toBeNumeric()` verifies these are
    // still sensible numbers without depending on that JSON-specific type collapse.
    expect($cart['adjustmentSubtotal'])->toBeNumeric();
    expect($cart['adjustmentsTotal'])->toBeNumeric();
    expect($cart['paymentCurrency'])->toBeString();
    expect($cart['paymentAmount'])->toBeNumeric();
    expect($cart['email'])->toBeNull();
    expect($cart['isPaid'])->toBeBool();
    expect($cart['itemSubtotal'])->toBeNumeric();
    expect($cart['itemTotal'])->toBeNumeric();
    expect($cart['lineItems'])->toBeArray();
    expect($cart['orderAdjustments'])->toBeArray();
    expect($cart['outstandingBalance'])->toBeNumeric();
    expect($cart['paidStatus'])->toBeString();
    expect($cart['recalculationMode'])->toBeString();
    expect($cart['shortNumber'])->toBeString();
    expect($cart['totalPaid'])->toBeNumeric();
    expect($cart['total'])->toBeNumeric();
    expect($cart['totalPrice'])->toBeNumeric();
    expect($cart['totalQty'])->toBeInt();
    expect($cart['totalSaleAmount'])->toBeNumeric();
    expect($cart['totalPromotionalAmount'])->toBeNumeric();
    expect($cart['totalWeight'])->toBeNumeric();
    expect($cart['adjustmentSubtotalAsCurrency'])->toBeString();
    expect($cart['adjustmentsTotalAsCurrency'])->toBeString();
    expect($cart['itemSubtotalAsCurrency'])->toBeString();
    expect($cart['itemTotalAsCurrency'])->toBeString();
    expect($cart['outstandingBalanceAsCurrency'])->toBeString();
    expect($cart['paymentAmountAsCurrency'])->toBeString();
    expect($cart['totalPaidAsCurrency'])->toBeString();
    expect($cart['totalAsCurrency'])->toBeString();
    expect($cart['totalPriceAsCurrency'])->toBeString();
    expect($cart['totalPromotionalAmountAsCurrency'])->toBeString();
    expect($cart['totalSaleAmountAsCurrency'])->toBeString();
    expect($cart['totalTaxAsCurrency'])->toBeString();
    expect($cart['totalTaxIncludedAsCurrency'])->toBeString();
    expect($cart['totalShippingCostAsCurrency'])->toBeString();
    expect($cart['totalDiscountAsCurrency'])->toBeString();
    expect($cart['storedTotalPriceAsCurrency'])->toBeString();
    expect($cart['storedTotalPaidAsCurrency'])->toBeString();
    expect($cart['storedItemTotalAsCurrency'])->toBeString();
    expect($cart['storedItemSubtotalAsCurrency'])->toBeString();
    expect($cart['storedTotalShippingCostAsCurrency'])->toBeString();
    expect($cart['storedTotalDiscountAsCurrency'])->toBeString();
    expect($cart['storedTotalTaxAsCurrency'])->toBeString();
    expect($cart['storedTotalTaxIncludedAsCurrency'])->toBeString();
    expect($cart['paidStatusHtml'])->toBeString();
    expect($cart['customerLinkHtml'])->toBeString();
    expect($cart['orderStatusHtml'])->toBeString();
    expect($cart['totalTax'])->toBeNumeric();
    expect($cart['totalTaxIncluded'])->toBeNumeric();
    expect($cart['totalShippingCost'])->toBeNumeric();
    expect($cart['totalDiscount'])->toBeNumeric();
    expect($cart['availableShippingMethodOptions'])->toBeArray();
    expect($cart['notices'])->toBeArray();
    expect($cart['billingAddress'])->toBeNull();
    expect($cart['shippingAddress'])->toBeNull();
});

it('adds a single purchasable to the cart', function() {
    $fixture = SalesFixture::seed();

    $response = postJson(Url::actionUrl('commerce/cart/update-cart'), [
        'purchasableId' => $fixture->radHood->id,
        'qty' => 2,
    ])->assertOk();

    $cart = $response->json('cart');

    expect($cart['lineItems'])->toHaveCount(1);
    expect($cart['totalQty'])->toBe(2);
    expect($cart['total'])->toEqual($fixture->radHood->getSalePrice() * 2);
});

it('adds multiple purchasables to the cart in a single request', function() {
    $fixture = SalesFixture::seed();

    $response = postJson(Url::actionUrl('commerce/cart/update-cart'), [
        'purchasables' => [
            ['id' => $fixture->radHood->id, 'qty' => 1],
            ['id' => $fixture->hctWhite->id, 'qty' => 2],
        ],
    ])->assertOk();

    expect($response->json('cart.lineItems'))->toHaveCount(2);
});

it('sets custom field values on shipping and billing addresses when updating the cart', function() {
    $field = new PlainText(['name' => 'Test Phone', 'handle' => 'testPhone']);
    expect(app(Fields::class)->saveField($field))->toBeTrue();

    $fieldLayout = app(Addresses::class)->getFieldLayout();
    $fieldLayout->tab(FieldLayout::defaultTabName(), fn($tab) => $tab->field($field->handle));
    expect(app(Addresses::class)->saveFieldLayout($fieldLayout))->toBeTrue();

    $shippingAddress = [
        'addressLine1' => '1 Main Street',
        'locality' => 'Bend',
        'administrativeArea' => 'OR',
        'postalCode' => '97701',
        'countryCode' => 'US',
        'fields' => ['testPhone' => '12345'],
    ];
    $billingAddress = [
        'addressLine1' => '100 Main Street',
        'locality' => 'Bend',
        'administrativeArea' => 'OR',
        'postalCode' => '97701',
        'countryCode' => 'US',
        'fields' => ['testPhone' => '67890'],
    ];

    $response = postJson(Url::actionUrl('commerce/cart/update-cart'), [
        'shippingAddress' => $shippingAddress,
        'billingAddress' => $billingAddress,
    ])->assertOk();

    $cart = $response->json('cart');

    expect($cart['shippingAddress']['addressLine1'])->toBe($shippingAddress['addressLine1']);
    expect($cart['shippingAddress']['testPhone'])->toBe($shippingAddress['fields']['testPhone']);
    expect($cart['billingAddress']['addressLine1'])->toBe($billingAddress['addressLine1']);
    expect($cart['billingAddress']['testPhone'])->toBe($billingAddress['fields']['testPhone']);
});

it('auto-sets the customer\'s primary shipping address on a new cart according to the store setting', function(bool $autoSet) {
    $salesFixture = SalesFixture::seed();
    $cartsFixture = CartsFixture::seed();

    app(Stores::class)->getPrimaryStore()->setAutoSetNewCartAddresses($autoSet);

    actingAs($cartsFixture->credentialedUser);

    $response = postJson(Url::actionUrl('commerce/cart/update-cart'), [
        'purchasableId' => $salesFixture->hoodie->getDefaultVariant()->id,
        'qty' => 2,
    ])->assertOk();

    $shippingAddress = $response->json('cart.shippingAddress');

    if ($autoSet) {
        expect($shippingAddress['addressLine1'])->toBe('23 Woodworth');
    } else {
        expect($shippingAddress)->toBeNull();
    }
})->with([
    'auto-set enabled' => [true],
    'auto-set disabled' => [false],
]);

it('sets which addresses should be saved to the customer\'s address book on order completion', function(?bool $saveBilling, ?bool $saveShipping, ?bool $saveBoth) {
    $bodyParams = [];
    if ($saveBoth) {
        $bodyParams['saveAddressesOnOrderComplete'] = true;
    } else {
        $bodyParams['saveBillingAddressOnOrderComplete'] = $saveBilling;
        $bodyParams['saveShippingAddressOnOrderComplete'] = $saveShipping;
    }

    $response = postJson(Url::actionUrl('commerce/cart/update-cart'), $bodyParams)->assertOk();

    $cart = $response->json('cart');

    if ($saveBoth) {
        expect($cart['saveBillingAddressOnOrderComplete'])->toBeTrue();
        expect($cart['saveShippingAddressOnOrderComplete'])->toBeTrue();
    } else {
        expect($cart['saveBillingAddressOnOrderComplete'])->toBe($saveBilling);
        expect($cart['saveShippingAddressOnOrderComplete'])->toBe($saveShipping);
    }
})->with([
    'save billing address only' => [true, false, false],
    'save shipping address only' => [false, true, false],
    'save both addresses individually' => [true, true, false],
    'save both addresses via the combined flag' => [false, false, true],
]);

it('sets shipping and/or billing addresses on the cart from the customer\'s address book', function(string $whichAddress, bool $validShipping, bool $validBilling) {
    $salesFixture = SalesFixture::seed();
    $cartsFixture = CartsFixture::seed();

    // Mirrors what a project's own custom validation rules could do to any Commerce-managed
    // address — `addressLine1` isn't required by Craft's own address rules for every country, so
    // this is the most direct way to force one of the customer's addresses to fail validation.
    Event::listen(function(ValidationRulesResolving $event) {
        if (!$event->subject instanceof Address) {
            return;
        }

        $event->addRule('addressLine1', 'required');
    });

    if (!$validShipping || !$validBilling) {
        DB::table(CraftTable::ADDRESSES)
            ->where('id', $cartsFixture->credentialedUserAddressId)
            ->update(['addressLine1' => null]);
    }

    actingAs($cartsFixture->credentialedUser);

    $bodyParams = [
        'purchasableId' => $salesFixture->hoodie->getDefaultVariant()->id,
        'qty' => 2,
    ];

    if ($whichAddress === 'shipping' || $whichAddress === 'both') {
        $bodyParams['shippingAddressId'] = $cartsFixture->credentialedUserAddressId;
    }

    if ($whichAddress === 'billing' || $whichAddress === 'both') {
        $bodyParams['billingAddressId'] = $cartsFixture->credentialedUserAddressId;
    }

    $response = postJson(Url::actionUrl('commerce/cart/update-cart'), $bodyParams);

    $shippingInvalid = ($whichAddress === 'shipping' || $whichAddress === 'both') && !$validShipping;
    $billingInvalid = ($whichAddress === 'billing' || $whichAddress === 'both') && !$validBilling;

    if ($shippingInvalid || $billingInvalid) {
        $response->assertStatus(400);
        $errorKeys = array_keys($response->json('errors', []));

        if ($shippingInvalid) {
            expect($response->json('cart.shippingAddress'))->toBeNull();
            expect(array_any($errorKeys, fn($key) => str_starts_with($key, 'shippingAddress')))->toBeTrue();
        }

        if ($billingInvalid) {
            expect($response->json('cart.billingAddress'))->toBeNull();
            expect(array_any($errorKeys, fn($key) => str_starts_with($key, 'billingAddress')))->toBeTrue();
        }

        return;
    }

    $response->assertOk();

    if ($whichAddress === 'shipping' || $whichAddress === 'both') {
        expect($response->json('cart.shippingAddress.addressLine1'))->toBe('23 Woodworth');
    }

    if ($whichAddress === 'billing' || $whichAddress === 'both') {
        expect($response->json('cart.billingAddress.addressLine1'))->toBe('23 Woodworth');
    }
})->with([
    'sets the shipping address' => ['shipping', true, true],
    'sets the billing address' => ['billing', true, true],
    'sets both addresses' => ['both', true, true],
    'fails validation when the shipping address is invalid' => ['shipping', false, true],
]);
