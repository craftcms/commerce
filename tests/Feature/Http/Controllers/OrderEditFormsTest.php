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

it('edits line item options as JSON', function() {
    $cart = editFormsCart(OrdersFixture::seed());
    $uid = $cart->getLineItems()[0]->uid;

    editFormsSubmit($cart, 'setLineItemOptions', ['lineItemUid' => $uid, 'options' => '[not json'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['options']);

    editFormsSubmit($cart, 'setLineItemOptions', ['lineItemUid' => $uid, 'options' => '{"engraving": "Hello"}'])
        ->assertOk();

    expect(Order::find()->id($cart->id)->one()->getLineItems()[0]->getOptions())->toBe(['engraving' => 'Hello']);
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

it('doesn’t save forms straight to a completed order', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];

    editFormsSubmit($order, 'setCouponCode', ['couponCode' => 'X'])->assertStatus(400);
});
