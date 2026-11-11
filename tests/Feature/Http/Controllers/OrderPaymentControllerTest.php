<?php

declare(strict_types=1);

use CraftCms\Cms\Activity\Data\ActivitySubject;
use CraftCms\Cms\Cms;
use CraftCms\Cms\Support\Facades\Activities;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\SystemMessage\Mailables\SystemMessageMailable;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Order\Activity\OrderPaymentLinkChanged;
use CraftCms\Commerce\Order\Activity\OrderTransactionSaved;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Payment\Data\Transaction;
use CraftCms\Commerce\Payment\Gateway\Gateway;
use CraftCms\Commerce\Payment\Gateway\Gateways;
use CraftCms\Commerce\Payment\Gateway\Types\Dummy;
use CraftCms\Commerce\Payment\Models\Transaction as TransactionRecord;
use CraftCms\Commerce\Payment\PaymentLinks;
use CraftCms\Commerce\Payment\Transactions;
use CraftCms\Commerce\Tests\Support\LinkGateway;
use CraftCms\Commerce\Tests\Support\OrdersFixture;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
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

function paymentTestGateway(Order $order, string $type = Dummy::class): Gateway
{
    $gateways = app(Gateways::class);
    $gateway = $gateways->createGateway([
        'type' => $type,
        'name' => 'Dummy',
        'handle' => 'orderPaymentDummy',
        'isFrontendEnabled' => true,
    ]);
    $gateways->saveGateway($gateway);

    $order->gatewayId = $gateway->id;
    Elements::saveElement($order, false);

    return $gateway;
}

function paymentTestTransaction(Order $order, string $type, ?Transaction $parent = null, array $attributes = []): Transaction
{
    $transactions = app(Transactions::class);
    $transaction = $transactions->createTransaction($parent ? null : $order, $parent, $type);

    foreach ($attributes as $name => $value) {
        $transaction->$name = $value;
    }

    $transaction->status = TransactionRecord::STATUS_SUCCESS;
    $transaction->reference = 'ref-' . $type;
    $transaction->response = json_encode(['id' => 'ch_123', 'paid' => true]);
    $transactions->saveTransaction($transaction);

    return $transaction;
}

function paymentTestEditing(Order $order): void
{
    postJson(Url::actionUrl('commerce/orders/edit/start'), ['orderId' => $order->id])->assertOk();
    postJson(Url::actionUrl('commerce/orders/edit/add'), [
        'orderId' => $order->id,
        'operation' => ['type' => 'setReference', 'reference' => 'PO-PAYMENT'],
    ])->assertOk();
}

function paymentTestActivity(Order $order, string $eventType): Illuminate\Support\Collection
{
    return Activities::query()
        ->subject(ActivitySubject::fromElement($order))
        ->where('eventType', $eventType)
        ->get();
}

function paymentTestUrl(string $path, array $params = []): string
{
    return Url::actionUrl("commerce/orders/payment/$path", $params);
}

function paymentTestRecord(Order $order, array $overrides = []): Illuminate\Testing\TestResponse
{
    return postJson(paymentTestUrl('record-payment'), [
        'orderId' => $order->id,
        'amount' => ['value' => (string)$order->outstandingBalance, 'locale' => 'en-US'],
        'reference' => 'BT-1',
        'note' => 'Paid by phone',
        ...$overrides,
    ]);
}

it('lists a transaction’s details, with its gateway response', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    paymentTestGateway($order);
    $purchase = paymentTestTransaction($order, TransactionRecord::TYPE_PURCHASE);

    $response = getJson(paymentTestUrl('transaction', ['id' => $purchase->id]))->assertOk();

    expect(collect($response->json('rows'))->pluck('value', 'label')->all())
        ->toMatchArray(['Reference' => 'ref-purchase', 'Gateway' => 'Dummy', 'Type' => 'Purchase'])
        ->and(json_decode($response->json('response'), true))->toBe(['id' => 'ch_123', 'paid' => true]);
});

it('opens a refund form in the payment currency, up to the refundable amount', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    paymentTestGateway($order);
    $purchase = paymentTestTransaction($order, TransactionRecord::TYPE_PURCHASE);

    getJson(paymentTestUrl('refund-form', ['id' => $purchase->id]))
        ->assertOk()
        ->assertJsonPath('title', 'Refund')
        ->assertJsonPath('ui.values.amount.value', $purchase->getRefundableAmount());
});

it('doesn’t open a refund form for a transaction that can’t be refunded', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    paymentTestGateway($order);
    $authorize = paymentTestTransaction($order, TransactionRecord::TYPE_AUTHORIZE);

    getJson(paymentTestUrl('refund-form', ['id' => $authorize->id]))->assertStatus(422);
});

it('records a manual payment that completes the order like a gateway payment', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    $outstanding = $order->outstandingBalance;

    paymentTestRecord($order)->assertOk()->assertJsonPath('message', 'Payment recorded.');

    $transaction = collect(app(Transactions::class)->getAllTransactionsByOrderId($order->id))->first(fn($transaction) => $transaction->isManual());
    $fresh = Order::find()->id($order->id)->one();

    expect($transaction->type)->toBe(TransactionRecord::TYPE_PURCHASE)
        ->and($transaction->status)->toBe(TransactionRecord::STATUS_SUCCESS)
        ->and($transaction->gatewayId)->toBeNull()
        ->and($transaction->isManual())->toBeTrue()
        ->and($transaction->reference)->toBe('BT-1')
        ->and($transaction->note)->toBe('Paid by phone')
        ->and($transaction->userId)->not->toBeNull()
        ->and((float)$transaction->amount)->toBe((float)$outstanding)
        ->and($fresh->getIsPaid())->toBeTrue()
        ->and($fresh->datePaid)->not->toBeNull();

    $activity = paymentTestActivity($order, OrderTransactionSaved::class);
    expect($activity)->toHaveCount(1)
        ->and(OrderTransactionSaved::format($activity->first()))->toContain('Recorded a')->toContain('BT-1');
});

it('needs a confirmation to record a payment over the outstanding balance', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    $overpayment = ['amount' => ['value' => (string)($order->outstandingBalance + 10), 'locale' => 'en-US']];

    paymentTestRecord($order, $overpayment)->assertStatus(422)->assertJsonValidationErrorFor('confirmed', 'errors');

    expect(app(Transactions::class)->getAllTransactionsByOrderId($order->id))->toBe([]);
});

it('lets a confirmed manual payment go over the outstanding balance', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];

    paymentTestRecord($order, ['amount' => ['value' => (string)($order->outstandingBalance + 10), 'locale' => 'en-US'], 'confirmed' => true])->assertOk();

    expect(Order::find()->id($order->id)->one()->getPaidStatus())->toBe(Order::PAID_STATUS_OVERPAID);
});

it('doesn’t ask for a confirmation to record exactly the outstanding balance', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];

    paymentTestRecord($order)->assertOk();
});

it('rejects a manual payment with no amount', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];

    paymentTestRecord($order, ['amount' => ['value' => '0', 'locale' => 'en-US']])->assertStatus(422);

    expect(app(Transactions::class)->getAllTransactionsByOrderId($order->id))->toBe([]);
});

it('doesn’t record a payment on a cart', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    $order->isCompleted = false;
    Elements::saveElement($order, false);

    paymentTestRecord($order)->assertStatus(400);
});

it('forbids recording payments and refunds without their permissions', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    Gate::before(fn($user, $ability) => in_array($ability, ['commerce-recordPayments', 'commerce-recordRefunds'], true) ? false : null);

    paymentTestRecord($order)->assertForbidden();
    getJson(paymentTestUrl('record-payment-form', ['orderId' => $order->id]))->assertForbidden();
    getJson(paymentTestUrl('record-refund-form', ['orderId' => $order->id]))->assertForbidden();
});

it('forbids creating and emailing payment links without the permission to record payments', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    $gateway = paymentTestGateway($order, LinkGateway::class);
    Gate::before(fn($user, $ability) => $ability === 'commerce-recordPayments' ? false : null);

    getJson(paymentTestUrl('link-form', ['orderId' => $order->id]))->assertForbidden();
    postJson(paymentTestUrl('link'), ['orderId' => $order->id, 'gatewayId' => $gateway->id])->assertForbidden();
    getJson(paymentTestUrl('link-url', ['orderId' => $order->id, 'transactionId' => 1]))->assertForbidden();
    postJson(paymentTestUrl('link-email'), ['orderId' => $order->id, 'transactionId' => 1])->assertForbidden();

    expect(app(PaymentLinks::class)->openLinks($order))->toBe([]);
});

it('doesn’t offer payment links or virtual terminals to a user who can’t record payments', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    paymentTestGateway($order, LinkGateway::class);
    $path = '/' . Cms::config()->cpTrigger . "/commerce/orders/{$order->id}";

    get($path)->assertOk()->assertInertia(fn(AssertableInertia $page) => $page->where('payments.canCreateLink', true)->etc());

    Gate::before(fn($user, $ability) => $ability === 'commerce-recordPayments' ? false : null);

    get($path)->assertOk()->assertInertia(fn(AssertableInertia $page) => $page
        ->where('payments.canCreateLink', false)
        ->where('payments.terminals', [])
        ->etc()
    );
});

it('opens the record payment form defaulting to the outstanding balance', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];

    getJson(paymentTestUrl('record-payment-form', ['orderId' => $order->id]))
        ->assertOk()
        ->assertJsonPath('ui.values.amount.value', $order->outstandingBalance);
});

it('says recording a payment or refund does not move money', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    paymentTestRecord($order)->assertOk();

    expect(getJson(paymentTestUrl('record-payment-form', ['orderId' => $order->id]))->assertOk()->getContent())
        ->toContain('charge the customer');
    expect(getJson(paymentTestUrl('record-refund-form', ['orderId' => $order->id]))->assertOk()->getContent())
        ->toContain('refund the customer');
});

it('records a manual refund up to what the order has been paid', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    paymentTestRecord($order)->assertOk();
    $paid = Order::find()->id($order->id)->one()->getTotalPaid();

    postJson(paymentTestUrl('record-refund'), [
        'orderId' => $order->id,
        'amount' => ['value' => (string)($paid + 1), 'locale' => 'en-US'],
        'note' => 'Too much',
    ])->assertStatus(422);

    postJson(paymentTestUrl('record-refund'), [
        'orderId' => $order->id,
        'amount' => ['value' => '5', 'locale' => 'en-US'],
        'note' => 'Customer returned an item',
    ])->assertOk()->assertJsonPath('message', 'Refund recorded.');

    $fresh = Order::find()->id($order->id)->one();

    expect($fresh->getTotalPaid())->toBe(round($paid - 5, 2))
        ->and($fresh->getIsPaid())->toBeFalse();

    expect(OrderTransactionSaved::format(paymentTestActivity($order, OrderTransactionSaved::class)->first()))->toContain('Recorded a')->toContain('refund');
});

it('caps a manual refund linked to a payment at what is left of it', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    paymentTestRecord($order)->assertOk();
    $payment = collect(app(Transactions::class)->getAllTransactionsByOrderId($order->id))->first(fn($transaction) => $transaction->isManual());

    postJson(paymentTestUrl('record-refund'), [
        'orderId' => $order->id,
        'id' => $payment->id,
        'amount' => ['value' => (string)($payment->amount - 1), 'locale' => 'en-US'],
        'note' => 'Most of it',
    ])->assertOk();

    expect(app(Transactions::class)->getTransactionById($payment->id)->getRefundableAmount())->toBe(1.0);

    postJson(paymentTestUrl('record-refund'), [
        'orderId' => $order->id,
        'id' => $payment->id,
        'amount' => ['value' => '2', 'locale' => 'en-US'],
        'note' => 'More',
    ])->assertStatus(422);
});

it('needs a confirmation to record a refund against a gateway payment', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    paymentTestGateway($order);
    $purchase = paymentTestTransaction($order, TransactionRecord::TYPE_PURCHASE);
    $payload = [
        'orderId' => $order->id,
        'id' => $purchase->id,
        'amount' => ['value' => '1', 'locale' => 'en-US'],
        'note' => 'Handed back',
    ];

    postJson(paymentTestUrl('record-refund'), $payload)->assertStatus(422);
    postJson(paymentTestUrl('record-refund'), [...$payload, 'confirmed' => true])->assertOk();

    expect(app(Transactions::class)->getTransactionById($purchase->id)->getRefundableAmount())->toBe($purchase->paymentAmount - 1);
});

it('refuses every payment action while the order has an edit session with changes', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    paymentTestGateway($order, LinkGateway::class);
    $purchase = paymentTestTransaction($order, TransactionRecord::TYPE_PURCHASE);
    $authorize = paymentTestTransaction($order, TransactionRecord::TYPE_AUTHORIZE);
    paymentTestEditing($order);

    getJson(paymentTestUrl('refund-form', ['id' => $purchase->id]))
        ->assertStatus(423)
        ->assertJsonPath('message', 'Update or discard your changes to this order first.');
    getJson(paymentTestUrl('record-payment-form', ['orderId' => $order->id]))->assertStatus(423);
    getJson(paymentTestUrl('record-refund-form', ['orderId' => $order->id]))->assertStatus(423);
    getJson(paymentTestUrl('link-form', ['orderId' => $order->id]))->assertStatus(423);
    paymentTestRecord($order)->assertStatus(423);
    postJson(Url::actionUrl('commerce/orders/transaction-capture'), ['id' => $authorize->id])->assertStatus(423);
    postJson(Url::actionUrl('commerce/orders/transaction-refund'), [
        'id' => $purchase->id,
        'amount' => ['value' => '1', 'locale' => 'en-US'],
        'note' => 'No',
    ])->assertStatus(423);

    expect(collect(app(Transactions::class)->getAllTransactionsByOrderId($order->id))->pluck('type')->unique()->sort()->values()->all())
        ->toBe([TransactionRecord::TYPE_AUTHORIZE, TransactionRecord::TYPE_PURCHASE]);
});

it('takes the payment actions again once the edit session is discarded', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    paymentTestEditing($order);
    postJson(Url::actionUrl('commerce/orders/edit/discard'), ['orderId' => $order->id])->assertOk();

    getJson(paymentTestUrl('record-payment-form', ['orderId' => $order->id]))->assertOk();
});

it('creates a payment link as a pending transaction the gateway completes', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    $gateway = paymentTestGateway($order, LinkGateway::class);

    $response = postJson(paymentTestUrl('link'), [
        'orderId' => $order->id,
        'gatewayId' => $gateway->id,
        'amount' => ['value' => '10', 'locale' => 'en-US'],
    ])->assertOk();

    $link = app(Transactions::class)->getTransactionById($response->json('transactionId'));

    expect($link->status)->toBe(TransactionRecord::STATUS_PENDING)
        ->and($link->type)->toBe(TransactionRecord::TYPE_PURCHASE)
        ->and((float)$link->amount)->toBe(10.0)
        ->and($response->json('url'))->toBe('https://pay.example.test/' . $link->hash)
        ->and($link->reference)->toBe('link_' . $link->hash)
        ->and(app(PaymentLinks::class)->openLinks(Order::find()->id($order->id)->one()))->toHaveCount(1);

    expect(OrderPaymentLinkChanged::format(paymentTestActivity($order, OrderPaymentLinkChanged::class)->first()))->toContain('Created a');
    expect(Order::find()->id($order->id)->one()->getIsPaid())->toBeFalse();

    $webhook = app(Transactions::class)->createTransaction(null, $link);
    $webhook->status = TransactionRecord::STATUS_SUCCESS;
    app(Transactions::class)->saveTransaction($webhook);

    expect(app(PaymentLinks::class)->openLinks(Order::find()->id($order->id)->one()))->toBe([])
        ->and(Order::find()->id($order->id)->one()->getTotalPaid())->toBe(10.0);
});

it('emails an open payment link to the customer with the payment link system message', function() {
    Mail::fake();
    $order = OrdersFixture::seed()->orders['completed-new'];
    $gateway = paymentTestGateway($order, LinkGateway::class);
    $link = app(PaymentLinks::class)->create($order, $gateway, 10.0);

    postJson(paymentTestUrl('link-email'), ['orderId' => $order->id, 'id' => $link->id])->assertOk();

    Mail::assertSent(SystemMessageMailable::class, fn(SystemMessageMailable $mail) => $mail->key === 'commerce_payment_link'
        && $mail->hasTo($order->email)
        && $mail->variables['link'] === 'https://pay.example.test/' . $link->hash);
    expect(OrderPaymentLinkChanged::format(paymentTestActivity($order, OrderPaymentLinkChanged::class)->first()))->toContain('Emailed');

    app(PaymentLinks::class)->expire($link);
    postJson(paymentTestUrl('link-email'), ['orderId' => $order->id, 'id' => $link->id])->assertStatus(410);
});

it('doesn’t create a payment link above the outstanding balance or for a gateway without links', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    $link = paymentTestGateway($order, LinkGateway::class);

    postJson(paymentTestUrl('link'), [
        'orderId' => $order->id,
        'gatewayId' => $link->id,
        'amount' => ['value' => (string)($order->outstandingBalance + 1), 'locale' => 'en-US'],
    ])->assertStatus(422);

    $plain = app(Gateways::class)->createGateway(['type' => Dummy::class, 'name' => 'Plain', 'handle' => 'plainDummy', 'isFrontendEnabled' => true]);
    app(Gateways::class)->saveGateway($plain);

    postJson(paymentTestUrl('link'), [
        'orderId' => $order->id,
        'gatewayId' => $plain->id,
        'amount' => ['value' => '1', 'locale' => 'en-US'],
    ])->assertStatus(422);
});

it('expires an open link at the gateway and closes it with a failed child', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    $gateway = paymentTestGateway($order, LinkGateway::class);
    LinkGateway::$expired = [];
    $link = app(PaymentLinks::class)->create($order, $gateway, 10.0);

    app(PaymentLinks::class)->expireOpenLinks(Order::find()->id($order->id)->one());

    $children = app(Transactions::class)->getChildrenByTransactionId($link->id);

    expect(LinkGateway::$expired)->toBe([$link->reference])
        ->and($children)->toHaveCount(1)
        ->and($children[0]->status)->toBe(TransactionRecord::STATUS_FAILED)
        ->and($children[0]->code)->toBe(PaymentLinks::EXPIRED_CODE)
        ->and(app(PaymentLinks::class)->openLinks(Order::find()->id($order->id)->one()))->toBe([])
        ->and(OrderPaymentLinkChanged::format(paymentTestActivity($order, OrderPaymentLinkChanged::class)->first()))->toContain('expired');
    expect(paymentTestActivity($order, OrderTransactionSaved::class))->toHaveCount(0);
});

it('treats a link past its expiry as closed', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    $gateway = paymentTestGateway($order, LinkGateway::class);
    $link = app(PaymentLinks::class)->create($order, $gateway, 10.0);

    $this->travel(8)->days();

    expect(app(PaymentLinks::class)->hasExpired($link))->toBeTrue()
        ->and(app(PaymentLinks::class)->openLinks(Order::find()->id($order->id)->one()))->toBe([]);
});

it('offers collecting a payment while the order has an outstanding balance', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    paymentTestGateway($order, LinkGateway::class);
    $plain = app(Gateways::class)->createGateway(['type' => Dummy::class, 'name' => 'Plain', 'handle' => 'plainDummy', 'isFrontendEnabled' => true]);
    app(Gateways::class)->saveGateway($plain);
    $path = '/' . Cms::config()->cpTrigger . "/commerce/orders/{$order->id}";

    get($path)->assertOk()->assertInertia(fn(AssertableInertia $page) => $page
        ->where('payments.canCreateLink', true)
        ->where('payments.terminals.0.name', 'Dummy')
        ->where('payments.terminals.0.url', 'https://terminal.example.test/?ref=' . $order->number)
        ->has('payments.terminals', 1)
        ->etc()
    );
});

it('tells the page which payment actions the user can take', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    paymentTestGateway($order, LinkGateway::class);
    $purchase = paymentTestTransaction($order, TransactionRecord::TYPE_PURCHASE);
    $authorize = paymentTestTransaction($order, TransactionRecord::TYPE_AUTHORIZE);
    $path = '/' . Cms::config()->cpTrigger . "/commerce/orders/{$order->id}";

    get($path)->assertOk()->assertInertia(fn(AssertableInertia $page) => $page
        ->where('payments.canRecordPayment', true)
        ->where('transactions', fn($rows) => collect($rows)->firstWhere('id', $purchase->id)['canRefund'] === true
            && collect($rows)->firstWhere('id', $authorize->id)['canCapture'] === true)
        ->etc()
    );

    Gate::before(fn($user, $ability) => in_array($ability, ['commerce-refundPayment', 'commerce-capturePayment', 'commerce-recordPayments', 'commerce-recordRefunds'], true) ? false : null);

    get($path)->assertOk()->assertInertia(fn(AssertableInertia $page) => $page
        ->where('payments.canRecordPayment', false)
        ->where('payments.canRecordRefund', false)
        ->where('transactions', fn($rows) => collect($rows)->every(fn($row) => !$row['canRefund'] && !$row['canCapture'] && !$row['canRecordRefund']))
        ->etc()
    );
});

it('can’t take a payment from the control panel with card details', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    $gateway = paymentTestGateway($order);

    postJson(Url::cpUrl('actions/commerce/payments/pay'), [
        'number' => $order->number,
        'gatewayId' => $gateway->id,
        'paymentAmount' => ['value' => '1', 'locale' => 'en-US'],
    ])->assertStatus(400);

    expect(app(Transactions::class)->getAllTransactionsByOrderId($order->id))->toBe([]);
});
