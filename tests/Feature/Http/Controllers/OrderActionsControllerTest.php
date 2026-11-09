<?php

declare(strict_types=1);

use CraftCms\Aliases\Aliases;
use CraftCms\Cms\Activity\Data\ActivitySubject;
use CraftCms\Cms\Cms;
use CraftCms\Cms\Support\Facades\Activities;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Cms\View\TemplateMode;
use CraftCms\Cms\View\TemplateRoots;
use CraftCms\Commerce\Email\Data\Email;
use CraftCms\Commerce\Email\Emails;
use CraftCms\Commerce\Email\Events\MailSent;
use CraftCms\Commerce\Inventory\Inventory;
use CraftCms\Commerce\Order\Activity\OrderEdited;
use CraftCms\Commerce\Order\Activity\OrderEmailSent;
use CraftCms\Commerce\Order\Activity\OrderFulfilled;
use CraftCms\Commerce\Order\Activity\OrderStatusChanged;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\LineItem\LineItems;
use CraftCms\Commerce\Order\OrderStatuses;
use CraftCms\Commerce\Pdf\Data\Pdf;
use CraftCms\Commerce\Pdf\Pdfs;
use CraftCms\Commerce\Tests\Support\OrdersFixture;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function() {
    $this->withoutVite();
    actingAs(User::find()->admin(true)->one());
    prioritizeCommerceRoutes();
});

function actionsEmail(OrdersFixture $fixture): Email
{
    $templatesPath = dirname(__DIR__, 3) . '/Support/templates';
    Aliases::set('@templates', $templatesPath);
    app(TemplateRoots::class)->register(TemplateMode::Site, '', $templatesPath);

    $email = new Email();
    $email->storeId = $fixture->storeId;
    $email->name = 'Order Confirmation';
    $email->subject = 'Order Confirmation';
    $email->templatePath = 'emails/order-confirmation';
    $email->setSenderAddress('store@crafttest.com');
    app(Emails::class)->saveEmail($email);

    return $email;
}

function actionsPdf(OrdersFixture $fixture): void
{
    $pdf = new Pdf();
    $pdf->storeId = $fixture->storeId;
    $pdf->name = 'Packing Slip';
    $pdf->handle = 'packingSlip';
    $pdf->templatePath = 'shop/_pdf/order';
    $pdf->isDefault = true;
    app(Pdfs::class)->savePdf($pdf);
}

function actionsCommittedOrder(OrdersFixture $fixture, int $qty = 3): Order
{
    $white = $fixture->white;
    $white->inventoryTracked = true;
    Elements::saveElement($white, false);
    app(Inventory::class)->updatePurchasableInventoryLevel($white, 10);

    $order = new Order();
    $order->number = bin2hex(random_bytes(16));
    $order->storeId = $fixture->storeId;
    $order->setCustomerId($fixture->customer->id);
    Elements::saveElement($order, false);
    $order->setLineItems([app(LineItems::class)->create($order, ['purchasableId' => $white->id, 'qty' => $qty])]);
    Elements::saveElement($order, false);
    $order->markAsComplete();

    return Order::find()->id($order->id)->one();
}

function actionsEdit(Order $order, string $reference = 'PO-ACTIONS'): void
{
    postJson(Url::actionUrl('commerce/orders/edit/start'), ['orderId' => $order->id])->assertOk();
    postJson(Url::actionUrl('commerce/orders/edit/add'), [
        'orderId' => $order->id,
        'operation' => ['type' => 'setReference', 'reference' => $reference],
    ])->assertOk();
}

function actionsUrl(string $action, array $params = []): string
{
    return Url::actionUrl("commerce/orders/actions/$action", $params);
}

function actionsPageProps(Order $order, Closure $assert): void
{
    get('/' . Cms::config()->cpTrigger . "/commerce/orders/{$order->id}")
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page) => $assert($page));
}

it('offers the store’s enabled emails in a form, with the first selected', function() {
    $fixture = OrdersFixture::seed();
    $email = actionsEmail($fixture);

    getJson(actionsUrl('email-form', ['orderId' => $fixture->orders['completed-new']->id]))
        ->assertOk()
        ->assertJsonPath('title', 'Send Email')
        ->assertJsonPath('ui.values.emailId', (string)$email->id);
});

it('sends the chosen email for the order', function() {
    $fixture = OrdersFixture::seed();
    $order = $fixture->orders['completed-new'];
    $email = actionsEmail($fixture);
    $sent = [];
    Event::listen(MailSent::class, function(MailSent $event) use (&$sent) {
        $sent[] = $event;
    });

    postJson(actionsUrl('send-email'), ['orderId' => $order->id, 'emailId' => $email->id])
        ->assertOk()
        ->assertJsonPath('message', 'Email sent.');

    expect($sent)->toHaveCount(1);
});

it('refuses to send an email that doesn’t exist', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];

    postJson(actionsUrl('send-email'), ['orderId' => $order->id, 'emailId' => 999999])
        ->assertStatus(422)
        ->assertJsonPath('errors.emailId.0', 'Can not find enabled email.');
});

it('doesn’t send emails for a cart', function() {
    $fixture = OrdersFixture::seed();
    $cart = $fixture->orders['completed-new'];
    $cart->isCompleted = false;
    Elements::saveElement($cart, false);
    $email = actionsEmail($fixture);

    postJson(actionsUrl('send-email'), ['orderId' => $cart->id, 'emailId' => $email->id])->assertStatus(400);
});

it('forbids the actions to users who can’t view orders', function() {
    $fixture = OrdersFixture::seed();
    $order = $fixture->orders['completed-new'];
    $email = actionsEmail($fixture);
    Gate::before(fn($user, $ability) => $ability === 'commerce-manageOrders' ? false : null);

    postJson(actionsUrl('send-email'), ['orderId' => $order->id, 'emailId' => $email->id])->assertForbidden();
});

it('fulfils committed stock by the submitted quantities', function() {
    $order = actionsCommittedOrder(OrdersFixture::seed());
    $level = app(Inventory::class)->getInventoryFulfillmentLevels($order)->first();
    $key = "{$level->inventoryLocationId}-{$level->inventoryItemId}-{$level->lineItemId}";

    getJson(actionsUrl('fulfillment-form', ['orderId' => $order->id]))
        ->assertOk()
        ->assertJsonPath("ui.values.fulfillment.$key", 0);

    postJson(actionsUrl('fulfill'), ['orderId' => $order->id, 'fulfillment' => [$key => 2]])->assertOk();

    $level = app(Inventory::class)->getInventoryFulfillmentLevels($order)->first();

    expect($level->fulfilledQuantity)->toBe(2)
        ->and($level->outstandingCommittedQuantity)->toBe(1);

    postJson(actionsUrl('fulfill'), ['orderId' => $order->id, 'fulfillment' => [$key => -1]])->assertOk();

    expect(app(Inventory::class)->getInventoryFulfillmentLevels($order)->first()->fulfilledQuantity)->toBe(1);
});

it('refuses fulfilment without the edit orders permission', function() {
    $order = actionsCommittedOrder(OrdersFixture::seed());
    $key = '1-1-1';

    Gate::before(fn($user, $ability) => $ability === 'commerce-editOrders' ? false : null);

    getJson(actionsUrl('fulfillment-form', ['orderId' => $order->id]))->assertForbidden();
    postJson(actionsUrl('fulfill'), ['orderId' => $order->id, 'fulfillment' => [$key => 1]])->assertForbidden();

    $level = app(Inventory::class)->getInventoryFulfillmentLevels($order)->first();

    expect($level->fulfilledQuantity)->toBe(0);
});

it('ignores quantities for stock the order doesn’t have', function() {
    $order = actionsCommittedOrder(OrdersFixture::seed());

    postJson(actionsUrl('fulfill'), ['orderId' => $order->id, 'fulfillment' => ['1-1-999999' => 1]])->assertOk();

    expect(app(Inventory::class)->getInventoryFulfillmentLevels($order)->first()->fulfilledQuantity)->toBe(0);
});

it('has no fulfilment form for an order without committed stock', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];

    getJson(actionsUrl('fulfillment-form', ['orderId' => $order->id]))->assertStatus(422);
});

it('gives a cart’s link and nothing for a completed order', function() {
    $fixture = OrdersFixture::seed();
    $cart = new Order();
    $cart->number = bin2hex(random_bytes(16));
    $cart->storeId = $fixture->storeId;
    Elements::saveElement($cart, false);

    getJson(actionsUrl('share-cart', ['orderId' => $cart->id]))
        ->assertOk()
        ->assertJsonPath('url', fn(string $url) => str_contains($url, 'cart/load'));

    getJson(actionsUrl('share-cart', ['orderId' => $fixture->orders['completed-new']->id]))->assertStatus(400);
});

it('refuses every action while the order has an edit session with changes', function() {
    $fixture = OrdersFixture::seed();
    $order = actionsCommittedOrder($fixture);
    $email = actionsEmail($fixture);
    actionsEdit($order);

    getJson(actionsUrl('email-form', ['orderId' => $order->id]))
        ->assertStatus(423)
        ->assertJsonPath('message', 'Update or discard your changes to this order first.');
    postJson(actionsUrl('send-email'), ['orderId' => $order->id, 'emailId' => $email->id])->assertStatus(423);
    getJson(actionsUrl('fulfillment-form', ['orderId' => $order->id]))->assertStatus(423);
    postJson(actionsUrl('fulfill'), ['orderId' => $order->id, 'fulfillment' => []])->assertStatus(423);
    postJson(Url::actionUrl('commerce/orders/delete-order'), ['orderId' => $order->id])->assertStatus(423);

    expect(Order::find()->id($order->id)->exists())->toBeTrue();

    postJson(Url::actionUrl('commerce/orders/edit/discard'), ['orderId' => $order->id])->assertOk();
    getJson(actionsUrl('email-form', ['orderId' => $order->id]))->assertOk();
});

it('lists the PDFs and the email action for a completed order', function() {
    $fixture = OrdersFixture::seed();
    $order = $fixture->orders['completed-new'];
    actionsPdf($fixture);
    actionsEmail($fixture);

    actionsPageProps($order, fn(AssertableInertia $page) => $page
        ->where('canEmail', true)
        ->where('emails', fn($emails) => collect($emails)->isNotEmpty()
            && is_int(collect($emails)->first()['id'])
            && is_string(collect($emails)->first()['name']))
        ->where('pdfs', fn($pdfs) => collect($pdfs)->isNotEmpty()
            && str_contains(collect($pdfs)->first()['url'], 'downloads/pdf')
            && str_contains(collect($pdfs)->first()['url'], $order->number))
        ->where('immediateActionsBlockedReason', null)
        ->etc());
});

it('offers neither emails nor PDFs for a cart', function() {
    $fixture = OrdersFixture::seed();
    $cart = $fixture->orders['completed-new'];
    $cart->isCompleted = false;
    Elements::saveElement($cart, false);
    actionsEmail($fixture);

    actionsPageProps($cart, fn(AssertableInertia $page) => $page->where('canEmail', false)->where('emails', [])->where('pdfs', [])->etc());
});

it('keeps the PDFs and email in the page but blocked while the order is being edited', function() {
    $fixture = OrdersFixture::seed();
    $order = $fixture->orders['completed-new'];
    actionsPdf($fixture);
    actionsEmail($fixture);
    actionsEdit($order);

    actionsPageProps($order, fn(AssertableInertia $page) => $page
        ->where('canEmail', true)
        ->where('pdfs', fn($pdfs) => collect($pdfs)->isNotEmpty())
        ->where('immediateActionsBlockedReason', 'Update or discard your changes to this order first.')
        ->etc());
});

it('refuses staff a PDF download while the order is being edited', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    actionsEdit($order);

    get(Url::actionUrl('commerce/downloads/pdf', ['number' => $order->number]))->assertStatus(423);
});

it('tells the page whether there is stock to fulfil', function() {
    $fixture = OrdersFixture::seed();
    $order = actionsCommittedOrder($fixture);

    actionsPageProps($order, fn(AssertableInertia $page) => $page
        ->where('canFulfil', true)
        ->where('fulfilment', fn($rows) => collect($rows)->isNotEmpty()
            && collect(collect($rows)->first())->keys()->sort()->values()->all() === ['committed', 'fulfilled', 'item', 'location'])
        ->etc());
    actionsPageProps($fixture->orders['completed-new'], fn(AssertableInertia $page) => $page
        ->where('canFulfil', false)
        ->where('fulfilment', [])
        ->etc());
});

it('lists what has a snapshot and shows one on demand', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    $lineItem = $order->getLineItems()[0];

    actionsPageProps($order, fn(AssertableInertia $page) => $page
        ->where('snapshots', fn($snapshots) => collect($snapshots)->contains(
            fn($row) => $row['key'] === "lineItem-{$lineItem->id}"
                && $row['name'] === $lineItem->getDescription()
                && $row['type'] === 'Line Item'
        ))
        ->etc());

    getJson(Url::actionUrl('commerce/orders/snapshot', ['orderId' => $order->id, 'key' => "lineItem-{$lineItem->id}"]))
        ->assertOk()
        ->assertJsonPath('json', fn($json) => json_decode($json, true) === $lineItem->getSnapshot());

    getJson(Url::actionUrl('commerce/orders/snapshot', ['orderId' => $order->id, 'key' => 'lineItem-0']))
        ->assertNotFound();
});

it('tells the page which saved addresses can be copied to the customer', function() {
    $fixture = OrdersFixture::seed();
    $order = $fixture->orders['completed-new'];

    actionsPageProps($order, fn(AssertableInertia $page) => $page
        ->where('copyToUser.userId', $order->getCustomer()?->id)
        ->where('copyToUser.shippingAddressId', $order->shippingAddressId)
        ->etc());

    Gate::before(fn($user, $ability) => $ability === 'editUsers' ? false : null);

    actionsPageProps($order, fn(AssertableInertia $page) => $page->where('copyToUser', null)->etc());
});

it('offers the adjustment forms for a completed order’s session', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    postJson(Url::actionUrl('commerce/orders/edit/start'), ['orderId' => $order->id])->assertOk();

    getJson(Url::actionUrl('commerce/orders/edit/form', ['orderId' => $order->id, 'form' => 'addAdjustment']))
        ->assertOk()
        ->assertJsonPath('title', 'Add Adjustment');
});

it('adds, edits and removes a manual adjustment in the session', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    postJson(Url::actionUrl('commerce/orders/edit/start'), ['orderId' => $order->id])->assertOk();
    $order = Order::find()->id($order->id)->one();

    postJson(Url::actionUrl('commerce/orders/edit/form'), [
        'orderId' => $order->id,
        'dateUpdated' => $order->dateUpdated->format(DATE_ATOM),
        'form' => 'addAdjustment',
        'adjustmentType' => 'discount',
        'name' => 'Goodwill',
        'amount' => ['value' => '-5', 'locale' => 'en-US'],
        'description' => '',
        'included' => false,
        'lineItemUid' => '',
    ])->assertOk();

    postJson(Url::actionUrl('commerce/orders/edit/preview'), ['orderId' => $order->id])
        ->assertOk()
        ->assertJsonPath('session.edit.operations.0.type', 'addAdjustment')
        ->assertJsonPath('session.edit.operations.0.name', 'Goodwill');
});

it('recalculates a completed order from its session', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    postJson(Url::actionUrl('commerce/orders/edit/start'), ['orderId' => $order->id])->assertOk();

    postJson(Url::actionUrl('commerce/orders/edit/add'), [
        'orderId' => $order->id,
        'operation' => ['type' => 'recalculate'],
    ])->assertOk()->assertJsonPath('session.edit.operations.0.type', 'recalculate');
});

function actionsAdjustmentForm(Order $order, array $values): Illuminate\Testing\TestResponse
{
    return postJson(Url::actionUrl('commerce/orders/edit/form'), [
        'orderId' => $order->id,
        'dateUpdated' => $order->dateUpdated->format(DATE_ATOM),
        'form' => 'addAdjustment',
        'adjustmentType' => 'discount',
        'name' => 'Goodwill',
        'amount' => ['value' => '-5', 'locale' => 'en-US'],
        'description' => '',
        'included' => false,
        'lineItemUid' => '',
        ...$values,
    ]);
}

function actionsSessionOperations(Order $order): array
{
    return postJson(Url::actionUrl('commerce/orders/edit/preview'), ['orderId' => $order->id])
        ->assertOk()
        ->json('session.edit.operations');
}

it('only accepts a discount that is a negative amount', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    postJson(Url::actionUrl('commerce/orders/edit/start'), ['orderId' => $order->id])->assertOk();
    $order = Order::find()->id($order->id)->one();

    actionsAdjustmentForm($order, ['amount' => ['value' => '5', 'locale' => 'en-US']])
        ->assertStatus(422)
        ->assertJsonPath('errors.amount.0', 'A discount must be a negative amount.');

    actionsAdjustmentForm($order, ['amount' => ['value' => '0', 'locale' => 'en-US']])->assertStatus(422);
    actionsAdjustmentForm($order, ['adjustmentType' => 'shipping', 'name' => 'Courier', 'amount' => ['value' => '5', 'locale' => 'en-US']])->assertOk();

    expect(actionsSessionOperations($order))->toHaveCount(1);
});

it('adds a line item adjustment from the line item, preselecting it in the form', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    $lineItem = $order->getLineItems()[0];
    postJson(Url::actionUrl('commerce/orders/edit/start'), ['orderId' => $order->id])->assertOk();

    getJson(Url::actionUrl('commerce/orders/edit/form', ['orderId' => $order->id, 'form' => 'addAdjustment', 'lineItemUid' => $lineItem->uid]))
        ->assertOk()
        ->assertJsonPath('ui.values.lineItemUid', $lineItem->uid);
});

it('edits and removes an adjustment added earlier in the same session', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    postJson(Url::actionUrl('commerce/orders/edit/start'), ['orderId' => $order->id])->assertOk();
    $order = Order::find()->id($order->id)->one();

    actionsAdjustmentForm($order, [])->assertOk();
    $uid = actionsSessionOperations($order)[0]['uid'];

    expect($uid)->toBeString();

    getJson(Url::actionUrl('commerce/orders/edit/form', ['orderId' => $order->id, 'form' => 'updateAdjustment', 'adjustmentUid' => $uid]))
        ->assertOk()
        ->assertJsonPath('title', 'Edit Goodwill')
        ->assertJsonPath('ui.values.changes.amount', -5);

    postJson(Url::actionUrl('commerce/orders/edit/form'), [
        'orderId' => $order->id,
        'dateUpdated' => $order->dateUpdated->format(DATE_ATOM),
        'form' => 'updateAdjustment',
        'adjustmentUid' => $uid,
        'changes' => [
            'adjustmentType' => 'discount',
            'name' => 'Apology',
            'description' => '',
            'amount' => ['value' => '-7', 'locale' => 'en-US'],
            'included' => false,
        ],
    ])->assertOk();

    $operations = actionsSessionOperations($order);

    expect($operations)->toHaveCount(2)
        ->and($operations[1]['type'])->toBe('updateAdjustment')
        ->and($operations[1]['changes'])->toBe(['name' => 'Apology', 'amount' => -7]);

    postJson(Url::actionUrl('commerce/orders/edit/add'), [
        'orderId' => $order->id,
        'operation' => ['type' => 'removeAdjustment', 'adjustmentUid' => $uid],
    ])->assertOk();

    expect(actionsSessionOperations($order))->toBe([]);
});

it('refuses to edit a discount into a positive amount', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    postJson(Url::actionUrl('commerce/orders/edit/start'), ['orderId' => $order->id])->assertOk();
    $order = Order::find()->id($order->id)->one();
    actionsAdjustmentForm($order, [])->assertOk();
    $uid = actionsSessionOperations($order)[0]['uid'];

    postJson(Url::actionUrl('commerce/orders/edit/form'), [
        'orderId' => $order->id,
        'dateUpdated' => $order->dateUpdated->format(DATE_ATOM),
        'form' => 'updateAdjustment',
        'adjustmentUid' => $uid,
        'changes' => [
            'adjustmentType' => 'discount',
            'name' => 'Goodwill',
            'description' => '',
            'amount' => ['value' => '3', 'locale' => 'en-US'],
            'included' => false,
        ],
    ])->assertStatus(422)->assertJsonPath('errors', fn($errors) => isset($errors['changes.amount']));
});


function actionsActivity(Order $order, string $eventType): Illuminate\Support\Collection
{
    return Activities::query()
        ->subject(ActivitySubject::fromElement($order))
        ->where('eventType', $eventType)
        ->get();
}

it('records the email that was sent in the order’s activity', function() {
    $fixture = OrdersFixture::seed();
    $order = $fixture->orders['completed-new'];
    $email = actionsEmail($fixture);

    postJson(actionsUrl('send-email'), ['orderId' => $order->id, 'emailId' => $email->id])->assertOk();

    $events = actionsActivity($order, OrderEmailSent::class);

    expect($events)->toHaveCount(1)
        ->and($events->first()->data['email'])->toBe('Order Confirmation');
});

it('records the stock that was fulfilled in the order’s activity', function() {
    $order = actionsCommittedOrder(OrdersFixture::seed());
    $level = app(Inventory::class)->getInventoryFulfillmentLevels($order)->first();
    $key = "{$level->inventoryLocationId}-{$level->inventoryItemId}-{$level->lineItemId}";

    postJson(actionsUrl('fulfill'), ['orderId' => $order->id, 'fulfillment' => [$key => 2]])->assertOk();

    $events = actionsActivity($order, OrderFulfilled::class);

    expect($events)->toHaveCount(1)
        ->and($events->first()->data['movements'][0]['quantity'])->toBe(2)
        ->and($events->first()->data['movements'][0]['item'])->toBe($order->getLineItems()[0]->getDescription());
});

it('records a committed edit in the order’s activity, with what it did', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    actionsEdit($order, 'PO-ACTIVITY');

    postJson(Url::actionUrl('commerce/orders/edit/commit'), ['orderId' => $order->id])->assertOk();

    $events = actionsActivity($order, OrderEdited::class);

    expect($events)->toHaveCount(1)
        ->and($events->first()->data['summaries'])->not->toBeEmpty();
});

it('records a change of status in the order’s activity', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    $before = actionsActivity($order, OrderStatusChanged::class)->count();
    $statuses = app(OrderStatuses::class);
    $other = collect($statuses->getAllOrderStatuses($order->storeId))->first(fn($status) => $status->id !== $order->orderStatusId);
    $order->orderStatusId = $other->id;
    $order->message = 'Shipped today';
    Elements::saveElement($order, false);

    $events = actionsActivity($order, OrderStatusChanged::class);

    expect($events)->toHaveCount($before + 1)
        ->and($events->first()->data['newStatus'])->toBe($other->name)
        ->and($events->first()->data['message'])->toBe('Shipped today');
});

it('points a completed order at its activity timeline, and leaves carts without one', function() {
    $fixture = OrdersFixture::seed();
    $order = $fixture->orders['completed-new'];

    actionsPageProps($order, fn(AssertableInertia $page) => $page
        ->where('activity.elementType', Order::class)
        ->where('activity.elementId', $order->id)
        ->where('activity.url', fn(string $url) => str_contains($url, 'elements/activity'))
        ->etc());

    $order->isCompleted = false;
    Elements::saveElement($order, false);

    actionsPageProps($order, fn(AssertableInertia $page) => $page->where('activity', null)->etc());
});

it('keeps the status history the timeline can’t show', function() {
    $fixture = OrdersFixture::seed();
    $order = $fixture->orders['completed-new'];

    $email = actionsEmail($fixture);
    postJson(actionsUrl('send-email'), ['orderId' => $order->id, 'emailId' => $email->id])->assertOk();

    actionsPageProps($order, fn(AssertableInertia $page) => $page->has('histories', 0)->etc());
});
