<?php

declare(strict_types=1);

use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Str;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\LineItem\Enums\LineItemType;
use CraftCms\Commerce\Tests\Support\OrdersFixture;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function() {
    actingAs(User::find()->admin(true)->one());
    prioritizeCommerceRoutes();
});

function editFormsCart(OrdersFixture $fixture, bool $withLineItem = true): Order
{
    $cart = new Order();
    $cart->number = bin2hex(random_bytes(16));
    $cart->storeId = $fixture->storeId;
    Elements::saveElement($cart, false);

    if ($withLineItem) {
        $cart = Order::find()->id($cart->id)->one();
        postJson(Url::actionUrl('commerce/orders/apply'), [
            'orderId' => $cart->id,
            'dateUpdated' => $cart->dateUpdated->format(DATE_ATOM),
            'operations' => [
                ['type' => 'addLineItem', 'uid' => (string)Str::uuid(), 'purchasableId' => $fixture->white->id, 'qty' => 1],
            ],
        ])->assertOk();
    }

    return Order::find()->id($cart->id)->one();
}

function editFormsShow(Order $order, string $form, array $params = []): Illuminate\Testing\TestResponse
{
    return getJson(Url::actionUrl('commerce/orders/edit/form', ['orderId' => $order->id, 'form' => $form, ...$params]));
}

function editFormsSubmit(Order $order, string $form, array $values): Illuminate\Testing\TestResponse
{
    return postJson(Url::actionUrl('commerce/orders/edit/form'), [
        'orderId' => $order->id,
        'dateUpdated' => $order->dateUpdated->format(DATE_ATOM),
        'form' => $form,
        ...$values,
    ]);
}

/**
 * @return list<string>
 */
function editFormsControlPaths(array $nodes): array
{
    $paths = [];

    foreach ($nodes as $node) {
        if (isset($node['control']['path'])) {
            $paths[] = implode('.', (array)$node['control']['path']);
        }

        $paths = [...$paths, ...editFormsControlPaths($node['children'] ?? [])];
    }

    return $paths;
}

it('offers only the quantity of a cart’s purchasable line item', function() {
    $cart = editFormsCart(OrdersFixture::seed());
    $lineItem = $cart->getLineItems()[0];

    $response = editFormsShow($cart, 'updateLineItem', ['lineItemUid' => $lineItem->uid])->assertOk();

    expect(editFormsControlPaths($response->json('ui.nodes')))->toBe(['changes.qty'])
        ->and($response->json('ui.values.changes.qty'))->toBe(1)
        ->and($response->json('title'))->toContain($lineItem->getDescription());
});

it('saves a submitted form to the cart', function() {
    $cart = editFormsCart(OrdersFixture::seed());
    $lineItem = $cart->getLineItems()[0];

    editFormsSubmit($cart, 'updateLineItem', [
        'lineItemUid' => $lineItem->uid,
        'changes' => ['qty' => '3'],
    ])->assertOk()->assertJsonPath('message', 'Cart updated.');

    expect(Order::find()->id($cart->id)->one()->getLineItems()[0]->qty)->toBe(3);
});

it('adds a custom line item from localized money input', function() {
    $fixture = OrdersFixture::seed();
    $cart = editFormsCart($fixture, false);
    $values = editFormsShow($cart, 'addCustomLineItem')->assertOk()->json('ui.values');

    editFormsSubmit($cart, 'addCustomLineItem', [
        ...$values,
        'description' => 'Gift wrap',
        'sku' => 'WRAP',
        'qty' => '2',
        'price' => ['value' => '4.50', 'locale' => 'en-US'],
    ])->assertOk();

    $lineItem = Order::find()->id($cart->id)->one()->getLineItems()[0];

    expect($lineItem->type)->toBe(LineItemType::Custom)
        ->and($lineItem->getDescription())->toBe('Gift wrap')
        ->and($lineItem->qty)->toBe(2)
        ->and($lineItem->getPrice())->toEqual(4.5);
});

it('returns validation errors at the paths of the form’s controls', function() {
    $cart = editFormsCart(OrdersFixture::seed());

    editFormsSubmit($cart, 'updateLineItem', [
        'lineItemUid' => $cart->getLineItems()[0]->uid,
        'changes' => ['qty' => '0'],
    ])->assertStatus(422)->assertJsonValidationErrors(['changes.qty']);

    editFormsSubmit($cart, 'addCustomLineItem', [
        'sku' => 'WRAP',
        'price' => ['value' => '1', 'locale' => 'en-US'],
    ])->assertStatus(422)->assertJsonValidationErrors(['description']);
});

it('edits line item options as name and value rows', function() {
    $cart = editFormsCart(OrdersFixture::seed());
    $uid = $cart->getLineItems()[0]->uid;

    editFormsSubmit($cart, 'setLineItemOptions', [
        'lineItemUid' => $uid,
        'options' => [
            ['key' => 'engraving', 'value' => 'Hello'],
            ['key' => 'gift', 'value' => '{"wrap": true}'],
            ['key' => '', 'value' => 'ignored'],
        ],
    ])->assertOk();

    $cart = Order::find()->id($cart->id)->one();

    expect($cart->getLineItems()[0]->getOptions())->toBe(['engraving' => 'Hello', 'gift' => ['wrap' => true]]);

    $response = editFormsShow($cart, 'setLineItemOptions', ['lineItemUid' => $uid])->assertOk();

    expect($response->json('ui.values.options'))->toBe([
        ['key' => 'engraving', 'value' => 'Hello'],
        ['key' => 'gift', 'value' => "{\n    \"wrap\": true\n}"],
    ]);
});

it('colours the line item statuses', function() {
    $cart = editFormsCart(OrdersFixture::seed());

    $response = editFormsShow($cart, 'setLineItemStatus', ['lineItemUid' => $cart->getLineItems()[0]->uid])->assertOk();
    $options = $response->json('ui.nodes.0.control.props.options');

    expect($options[0]['value'])->toBe('')
        ->and(collect($options)->skip(1)->every(fn(array $option) => isset($option['data']['indicator']['fill'])))->toBeTrue();
});

it('treats an empty choice as none', function() {
    $cart = editFormsCart(OrdersFixture::seed());
    $cart->couponCode = 'OLD';
    Elements::saveElement($cart, false);
    $cart = Order::find()->id($cart->id)->one();

    editFormsSubmit($cart, 'setCouponCode', ['couponCode' => ' '])->assertOk();

    expect(Order::find()->id($cart->id)->one()->couponCode)->toBeNull();
});

it('returns a 404 for an unknown form', function() {
    $cart = editFormsCart(OrdersFixture::seed(), false);

    editFormsShow($cart, 'nope')->assertNotFound();
});

it('adds a completed order’s form to its edit session instead of saving it', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    $uid = $order->getLineItems()[0]->uid;

    editFormsSubmit($order, 'setCouponCode', ['couponCode' => 'X'])->assertNotFound();

    postJson(Url::actionUrl('commerce/orders/edit/start'), ['orderId' => $order->id])->assertOk();

    editFormsShow($order, 'setCouponCode')->assertOk();

    $changes = editFormsShow($order, 'updateLineItem', ['lineItemUid' => $uid])->assertOk()->json('ui.values.changes');

    editFormsSubmit($order, 'updateLineItem', ['lineItemUid' => $uid, 'changes' => [...$changes, 'qty' => '4']])
        ->assertOk()
        ->assertJsonPath('message', 'Change added.')
        ->assertJsonPath('session.edit.operations.0.type', 'updateLineItem');

    $saved = Order::find()->id($order->id)->one();

    expect($saved->getLineItems()[0]->qty)->toBe($order->getLineItems()[0]->qty);
});

it('shows a form the order as its session would leave it', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    $uid = $order->getLineItems()[0]->uid;
    postJson(Url::actionUrl('commerce/orders/edit/start'), ['orderId' => $order->id])->assertOk();
    $changes = editFormsShow($order, 'updateLineItem', ['lineItemUid' => $uid])->assertOk()->json('ui.values.changes');
    editFormsSubmit($order, 'updateLineItem', ['lineItemUid' => $uid, 'changes' => [...$changes, 'qty' => '4']])->assertOk();

    $response = editFormsShow($order, 'updateLineItem', ['lineItemUid' => $uid])->assertOk();

    expect($response->json('ui.values.changes.qty'))->toBe(4);
});

it('adds a purchasable chosen with the element selector', function() {
    $fixture = OrdersFixture::seed();
    $cart = editFormsCart($fixture, false);

    $response = editFormsShow($cart, 'addLineItem')->assertOk();

    expect(editFormsControlPaths($response->json('ui.nodes')))->toBe(['purchasableId', 'qty', 'note']);

    editFormsSubmit($cart, 'addLineItem', ['purchasableId' => $fixture->blue->id, 'qty' => '2'])->assertOk();

    $lineItem = Order::find()->id($cart->id)->one()->getLineItems()[0];

    expect($lineItem->purchasableId)->toBe($fixture->blue->id)->and($lineItem->qty)->toBe(2);

    editFormsSubmit($cart, 'addLineItem', ['qty' => '1'])->assertStatus(422)->assertJsonValidationErrors(['purchasableId']);
});

it('sets, creates and removes the customer', function() {
    $fixture = OrdersFixture::seed();
    $cart = editFormsCart($fixture, false);

    editFormsSubmit($cart, 'setCustomer', ['customerId' => $fixture->customer->id])->assertOk();
    $cart = Order::find()->id($cart->id)->one();
    expect($cart->getCustomerId())->toBe($fixture->customer->id);

    editFormsSubmit($cart, 'setCustomer', ['email' => 'new-customer@example.com'])->assertOk();
    $cart = Order::find()->id($cart->id)->one();
    expect($cart->getCustomer()->email)->toBe('new-customer@example.com');

    editFormsSubmit($cart, 'setCustomer', ['email' => 'not an email'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email']);

    editFormsSubmit($cart, 'setCustomer', [])->assertOk();
    expect(Order::find()->id($cart->id)->one()->getCustomerId())->toBeNull();
});

it('edits an address from its own fields', function() {
    $cart = editFormsCart(OrdersFixture::seed(), false);

    $paths = editFormsControlPaths(editFormsShow($cart, 'setAddress', ['addressType' => 'shipping'])->assertOk()->json('ui.nodes'));
    expect($paths)->toContain('countryCode', 'address');

    editFormsSubmit($cart, 'setAddress', [
        'addressType' => 'shipping',
        'fullName' => 'Ada Lovelace',
        'countryCode' => 'US',
        'address' => ['addressLine1' => '1 Main St', 'locality' => 'Portland', 'administrativeArea' => 'OR', 'postalCode' => '97201'],
    ])->assertOk();

    $address = Order::find()->id($cart->id)->one()->getShippingAddress();

    expect($address->fullName)->toBe('Ada Lovelace')
        ->and($address->addressLine1)->toBe('1 Main St')
        ->and($address->postalCode)->toBe('97201');

    editFormsSubmit(Order::find()->id($cart->id)->one(), 'setAddress', [
        'addressType' => 'shipping',
        'countryCode' => 'US',
        'address' => ['addressLine1' => '9 Other St', 'locality' => 'Portland', 'administrativeArea' => 'OR', 'postalCode' => '97201'],
    ])->assertOk();

    expect(Order::find()->id($cart->id)->one()->getShippingAddress()->fullName)->toBe('Ada Lovelace');
});

it('copies one of the customer’s addresses', function() {
    $fixture = OrdersFixture::seed();
    $cart = editFormsCart($fixture, false);
    editFormsSubmit($cart, 'setCustomer', ['customerId' => $fixture->customer->id])->assertOk();
    $cart = Order::find()->id($cart->id)->one();

    $address = new CraftCms\Cms\Address\Elements\Address([
        'ownerId' => $fixture->customer->id,
        'countryCode' => 'US',
        'addressLine1' => '2 Side St',
        'locality' => 'Salem',
        'administrativeArea' => 'OR',
        'postalCode' => '97301',
    ]);
    Elements::saveElement($address, false);

    $response = editFormsShow($cart, 'setAddress', ['addressType' => 'billing', 'mode' => 'book'])->assertOk();
    expect(editFormsControlPaths($response->json('ui.nodes')))->toBe(['sourceAddressId']);

    editFormsSubmit($cart, 'setAddress', ['addressType' => 'billing', 'mode' => 'book', 'sourceAddressId' => (string)$address->id])->assertOk();

    expect(Order::find()->id($cart->id)->one()->getBillingAddress()->addressLine1)->toBe('2 Side St');
});

it('refreshes the order’s custom fields screen', function() {
    $cart = editFormsCart(OrdersFixture::seed(), false);

    postJson(Url::actionUrl('commerce/orders/edit/fields', ['orderId' => $cart->id]), [
        'values' => ['fields' => []],
        'scope' => [],
    ])->assertOk()->assertJsonStructure(['ui' => ['nodes', 'values']]);
});

it('refreshes an address screen for the chosen country', function() {
    $cart = editFormsCart(OrdersFixture::seed(), false);

    $labels = function(string $countryCode) use ($cart): array {
        $response = postJson(Url::actionUrl('commerce/orders/edit/address', ['orderId' => $cart->id, 'addressType' => 'shipping']), [
            'values' => ['countryCode' => $countryCode],
            'scope' => [],
        ])->assertOk();

        $labels = [];
        $nodes = $response->json('ui.nodes');
        array_walk_recursive($nodes, function($value, $key) use (&$labels) {
            if ($key === 'label') {
                $labels[] = $value;
            }
        });

        return $labels;
    };

    expect($labels('US'))->toContain('State')->toContain('Zip Code')
        ->and($labels('CA'))->toContain('Province')->toContain('Postal Code');
});

it('sets and clears a cart’s reference', function() {
    $cart = editFormsCart(OrdersFixture::seed(), false);

    editFormsSubmit($cart, 'setReference', ['reference' => ' PO-1234 '])->assertOk();
    $cart = Order::find()->id($cart->id)->one();
    expect($cart->reference)->toBe('PO-1234');

    editFormsSubmit($cart, 'setReference', ['reference' => ''])->assertOk();
    expect(Order::find()->id($cart->id)->one()->reference)->toBeNull();
});
