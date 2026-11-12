<?php

declare(strict_types=1);

use CraftCms\Cms\Database\Table as CmsTable;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Str;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Order\Edit\OrderEditSessions;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Tests\Support\OrdersFixture;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

beforeEach(function() {
    actingAs(User::find()->admin(true)->one());
    prioritizeCommerceRoutes();
});

function editEndpointsAdmin(string $username): User
{
    $user = new User();
    $user->username = $username;
    $user->email = "$username@crafttest.com";
    $user->admin = true;
    Elements::saveElement($user);

    return $user;
}

function editEndpointsPost(string $action, array $data): Illuminate\Testing\TestResponse
{
    return postJson(Url::actionUrl("commerce/orders/$action"), $data);
}

function editEndpointsCart(OrdersFixture $fixture): Order
{
    $cart = new Order();
    $cart->number = bin2hex(random_bytes(16));
    $cart->storeId = $fixture->storeId;
    Elements::saveElement($cart, false);

    return Order::find()->id($cart->id)->one();
}

describe('carts', function() {
    it('applies operations and saves the cart', function() {
        $fixture = OrdersFixture::seed();
        $cart = editEndpointsCart($fixture);
        $uid = (string)Str::uuid();

        $response = editEndpointsPost('apply', [
            'orderId' => $cart->id,
            'dateUpdated' => $cart->dateUpdated->format(DATE_ATOM),
            'operations' => [
                ['type' => 'addLineItem', 'uid' => $uid, 'purchasableId' => $fixture->white->id, 'qty' => 2],
            ],
        ])->assertOk();

        expect($response->json('order.lineItems.0.uid'))->toBe($uid);
        expect($response->json('order.itemSubtotal'))->toEqual(round($fixture->white->getPrice() * 2, 2));
        expect(collect(Order::find()->id($cart->id)->one()->getLineItems())->firstWhere('uid', $uid)?->qty)->toBe(2);
    });

    it('rejects changes made against an earlier version of the cart', function() {
        $fixture = OrdersFixture::seed();
        $cart = editEndpointsCart($fixture);

        editEndpointsPost('apply', [
            'orderId' => $cart->id,
            'dateUpdated' => (clone $cart->dateUpdated)->modify('-1 hour')->format(DATE_ATOM),
            'operations' => [['type' => 'setReference', 'reference' => 'TOO-LATE']],
        ])->assertStatus(409);
    });

    it('flags the operation that doesn’t apply', function() {
        $cart = editEndpointsCart(OrdersFixture::seed());

        editEndpointsPost('apply', [
            'orderId' => $cart->id,
            'dateUpdated' => $cart->dateUpdated->format(DATE_ATOM),
            'operations' => [
                ['type' => 'setReference', 'reference' => 'REF'],
                ['type' => 'removeLineItem', 'lineItemUid' => 'missing'],
            ],
        ])->assertStatus(422)->assertJsonPath('operationIndex', 1);
    });

    it('returns validation errors keyed by operation', function() {
        $cart = editEndpointsCart(OrdersFixture::seed());

        editEndpointsPost('apply', [
            'orderId' => $cart->id,
            'dateUpdated' => $cart->dateUpdated->format(DATE_ATOM),
            'operations' => [['type' => 'addLineItem', 'uid' => (string)Str::uuid()]],
        ])->assertStatus(422)->assertJsonValidationErrors(['operations.0.purchasableId']);
    });

    it('doesn’t apply operations straight to a completed order', function() {
        $order = OrdersFixture::seed()->orders['completed-new'];

        editEndpointsPost('apply', [
            'orderId' => $order->id,
            'dateUpdated' => $order->dateUpdated->format(DATE_ATOM),
            'operations' => [['type' => 'setReference', 'reference' => 'REF']],
        ])->assertStatus(400);
    });

    it('doesn’t start an edit session for a cart', function() {
        $cart = editEndpointsCart(OrdersFixture::seed());

        editEndpointsPost('edit/start', ['orderId' => $cart->id])->assertStatus(400);
    });
});

describe('edit sessions', function() {
    it('starts, previews, adds, removes and commits', function() {
        $order = OrdersFixture::seed()->orders['completed-new'];

        editEndpointsPost('edit/start', ['orderId' => $order->id])
            ->assertOk()
            ->assertJsonPath('session.edit.operations', [])
            ->assertJsonPath('stale', false);

        $goodwill = ['type' => 'addAdjustment', 'adjustmentType' => 'discount', 'name' => 'Goodwill', 'amount' => -5];

        $preview = editEndpointsPost('edit/preview', ['orderId' => $order->id, 'operation' => $goodwill])->assertOk();
        expect($preview->json('order.totalPrice'))->toEqual($preview->json('original.totalPrice') - 5);
        expect($preview->json('session.edit.operations'))->toBe([]);

        editEndpointsPost('edit/add', ['orderId' => $order->id, 'operation' => ['type' => 'setReference', 'reference' => 'NEW-REF']])->assertOk();
        editEndpointsPost('edit/add', ['orderId' => $order->id, 'operation' => $goodwill])
            ->assertOk()
            ->assertJsonCount(2, 'session.edit.operations');

        editEndpointsPost('edit/remove', ['orderId' => $order->id, 'index' => 1])
            ->assertOk()
            ->assertJsonPath('session.edit.operations.0.type', 'setReference')
            ->assertJsonCount(1, 'session.edit.operations');

        editEndpointsPost('edit/commit', ['orderId' => $order->id])->assertOk();

        $saved = Order::find()->id($order->id)->one();
        expect($saved->reference)->toBe('NEW-REF');
        expect(collect($saved->getAdjustments())->pluck('name')->all())->not->toContain('Goodwill');
        expect(app(OrderEditSessions::class)->getSessionByOrderId($order->id))->toBeNull();
    });

    it('asks for a review when the order changed, then commits', function() {
        $order = OrdersFixture::seed()->orders['completed-new'];
        editEndpointsPost('edit/start', ['orderId' => $order->id])->assertOk();
        editEndpointsPost('edit/add', ['orderId' => $order->id, 'operation' => ['type' => 'setReference', 'reference' => 'NEW-REF']])->assertOk();
        DB::table(CmsTable::ELEMENTS)->where('id', $order->id)->update(['dateUpdated' => '2030-01-01 00:00:00']);

        editEndpointsPost('edit/preview', ['orderId' => $order->id])->assertOk()->assertJsonPath('stale', true);

        $conflict = editEndpointsPost('edit/commit', ['orderId' => $order->id])
            ->assertStatus(409)
            ->assertJsonPath('stale', false)
            ->assertJsonCount(1, 'session.edit.operations');

        expect(strtotime($conflict->json('session.edit.baseDateUpdated')))->toBe(strtotime('2030-01-01 00:00:00 UTC'));

        expect(Order::find()->id($order->id)->one()->reference)->toBe($order->reference);

        editEndpointsPost('edit/commit', ['orderId' => $order->id])->assertOk();
        expect(Order::find()->id($order->id)->one()->reference)->toBe('NEW-REF');
    });

    it('discards the session without saving', function() {
        $order = OrdersFixture::seed()->orders['completed-new'];
        editEndpointsPost('edit/start', ['orderId' => $order->id])->assertOk();
        editEndpointsPost('edit/add', ['orderId' => $order->id, 'operation' => ['type' => 'setReference', 'reference' => 'NEW-REF']])->assertOk();

        editEndpointsPost('edit/discard', ['orderId' => $order->id])->assertOk();

        expect(app(OrderEditSessions::class)->getSessionByOrderId($order->id))->toBeNull();
        expect(Order::find()->id($order->id)->one()->reference)->toBe($order->reference);
    });

    it('shows who is editing, and lets another admin take over', function() {
        $order = OrdersFixture::seed()->orders['completed-new'];
        $first = editEndpointsAdmin('first');
        actingAs($first);
        editEndpointsPost('edit/start', ['orderId' => $order->id])->assertOk();
        editEndpointsPost('edit/add', ['orderId' => $order->id, 'operation' => ['type' => 'setReference', 'reference' => 'NEW-REF']])->assertOk();

        $second = editEndpointsAdmin('second');
        actingAs($second);

        editEndpointsPost('edit/start', ['orderId' => $order->id])
            ->assertStatus(423)
            ->assertJsonPath('session.userId', $first->id);
        editEndpointsPost('edit/add', ['orderId' => $order->id, 'operation' => ['type' => 'setReference', 'reference' => 'MINE']])
            ->assertStatus(423);
        editEndpointsPost('edit/commit', ['orderId' => $order->id])->assertStatus(423);

        editEndpointsPost('edit/take-over', ['orderId' => $order->id])
            ->assertOk()
            ->assertJsonPath('session.userId', $second->id)
            ->assertJsonPath('session.edit.operations', []);

        actingAs($first);
        editEndpointsPost('edit/commit', ['orderId' => $order->id])->assertStatus(423);
        expect(Order::find()->id($order->id)->one()->reference)->toBe($order->reference);
    });

    it('needs the edit orders permission', function() {
        $order = OrdersFixture::seed()->orders['completed-new'];
        Gate::before(fn($user, $ability) => $ability === 'commerce-editOrders' ? false : null);

        editEndpointsPost('edit/start', ['orderId' => $order->id])->assertForbidden();
    });

    it('refuses item and address changes without the edit orders permission', function() {
        $order = OrdersFixture::seed()->orders['completed-new'];
        $lineItem = $order->getLineItems()[0];
        $qty = $lineItem->qty;
        $billingId = $order->getBillingAddress()?->id;
        Gate::before(fn($user, $ability) => $ability === 'commerce-editOrders' ? false : null);

        $changes = [
            ['type' => 'updateLineItem', 'lineItemUid' => $lineItem->uid, 'changes' => ['qty' => $qty + 5]],
            ['type' => 'removeLineItem', 'lineItemUid' => $lineItem->uid],
            ['type' => 'setAddress', 'addressType' => 'billing', 'address' => ['addressLine1' => '1 Nowhere Lane', 'countryCode' => 'US']],
        ];

        foreach ($changes as $operation) {
            editEndpointsPost('edit/add', ['orderId' => $order->id, 'operation' => $operation])->assertForbidden();
            editEndpointsPost('edit/preview', ['orderId' => $order->id, 'operation' => $operation])->assertForbidden();
            editEndpointsPost('apply', ['orderId' => $order->id, 'operations' => [$operation]])->assertForbidden();
        }

        editEndpointsPost('edit/commit', ['orderId' => $order->id])->assertForbidden();
        editEndpointsPost('edit/discard', ['orderId' => $order->id])->assertForbidden();

        $fresh = Order::find()->id($order->id)->one();

        expect(app(OrderEditSessions::class)->getSessionByOrderId($order->id))->toBeNull()
            ->and($fresh->getLineItems())->toHaveCount(count($order->getLineItems()))
            ->and(collect($fresh->getLineItems())->firstWhere('uid', $lineItem->uid)->qty)->toBe($qty)
            ->and($fresh->getBillingAddress()?->id)->toBe($billingId);
    });

    it('returns a 404 when there’s no session', function() {
        $order = OrdersFixture::seed()->orders['completed-new'];

        editEndpointsPost('edit/commit', ['orderId' => $order->id])->assertNotFound();
    });
});
