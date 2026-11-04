<?php

declare(strict_types=1);

use craft\commerce\events\PaymentCurrencyRateEvent;
use craft\commerce\events\PaymentSourceEvent;
use craft\commerce\events\ProcessPaymentEvent;
use craft\commerce\events\RefundTransactionEvent;
use craft\commerce\events\TransactionEvent;
use craft\commerce\events\UpdatePrimaryPaymentSourceEvent;
use craft\commerce\events\WebhookEvent;
use craft\commerce\services\Customers as LegacyCustomers;
use craft\commerce\services\PaymentCurrencies as LegacyPaymentCurrencies;
use craft\commerce\services\Payments as LegacyPayments;
use craft\commerce\services\PaymentSources as LegacyPaymentSources;
use craft\commerce\services\Transactions as LegacyTransactions;
use craft\commerce\services\Webhooks as LegacyWebhooks;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Payment\Data\PaymentCurrency;
use CraftCms\Commerce\Payment\Data\PaymentSource;
use CraftCms\Commerce\Payment\Data\Transaction;
use CraftCms\Commerce\Payment\Events\PaymentCurrencyRateResolving;
use CraftCms\Commerce\Payment\Events\PaymentProcessing;
use CraftCms\Commerce\Payment\Events\PaymentSourceSaving;
use CraftCms\Commerce\Payment\Events\PrimaryPaymentSourceUpdated;
use CraftCms\Commerce\Payment\Events\TransactionCaptured;
use CraftCms\Commerce\Payment\Events\TransactionRefunding;
use CraftCms\Commerce\Payment\Events\TransactionSaved;
use CraftCms\Commerce\Payment\Events\WebhookProcessed;
use CraftCms\Commerce\Payment\Forms\CreditCardPaymentForm;
use CraftCms\Commerce\Payment\Gateway\Types\Dummy;
use Illuminate\Http\Response;
use yii\base\Event as YiiEvent;

// These test the legacy craft\commerce event bridge, which needs the yii2 adapter.
pest()->group('yii2-adapter');

afterEach(function() {
    YiiEvent::offAll();
});

test('legacy listeners can cancel payment processing', function() {
    $order = new Order();
    $received = null;

    YiiEvent::on(LegacyPayments::class, LegacyPayments::EVENT_BEFORE_PROCESS_PAYMENT, function(ProcessPaymentEvent $event) use (&$received) {
        $received = $event->order;
        $event->isValid = false;
    });

    event($event = new PaymentProcessing($order, new CreditCardPaymentForm()));

    expect($received)->toBe($order)
        ->and($event->isValid)->toBeFalse();
});

test('legacy listeners receive transaction captures and refunds', function() {
    $transaction = new Transaction();
    $received = [];

    YiiEvent::on(LegacyPayments::class, LegacyPayments::EVENT_AFTER_CAPTURE_TRANSACTION, function(TransactionEvent $event) use (&$received) {
        $received[] = $event->transaction;
    });
    YiiEvent::on(LegacyPayments::class, LegacyPayments::EVENT_BEFORE_REFUND_TRANSACTION, function(RefundTransactionEvent $event) use (&$received) {
        $received[] = $event->amount;
    });
    YiiEvent::on(LegacyTransactions::class, LegacyTransactions::EVENT_AFTER_SAVE_TRANSACTION, function(TransactionEvent $event) use (&$received) {
        $received[] = 'saved';
    });

    event(new TransactionCaptured($transaction));
    event(new TransactionRefunding($transaction, 12.5));
    event(new TransactionSaved($transaction));

    expect($received)->toBe([$transaction, 12.5, 'saved']);
});

test('legacy listeners can cancel saving a payment source', function() {
    YiiEvent::on(LegacyPaymentSources::class, LegacyPaymentSources::EVENT_BEFORE_SAVE_PAYMENT_SOURCE, function(PaymentSourceEvent $event) {
        $event->isValid = false;
    });

    event($event = new PaymentSourceSaving(new PaymentSource()));

    expect($event->isValid)->toBeFalse();
});

test('legacy listeners can change a payment currency rate', function() {
    YiiEvent::on(LegacyPaymentCurrencies::class, LegacyPaymentCurrencies::EVENT_DEFINE_PAYMENT_CURRENCY_RATE, function(PaymentCurrencyRateEvent $event) {
        $event->rate *= 2;
    });

    event($event = new PaymentCurrencyRateResolving(1.5, new PaymentCurrency()));

    expect($event->rate)->toBe(3.0);
});

test('legacy listeners receive primary payment source changes and processed webhooks', function() {
    $customer = new User();
    $response = new Response('ok');
    $received = [];

    YiiEvent::on(LegacyCustomers::class, LegacyCustomers::EVENT_UPDATE_PRIMARY_PAYMENT_SOURCE, function(UpdatePrimaryPaymentSourceEvent $event) use (&$received) {
        $received[] = [$event->customer, $event->previousPrimaryPaymentSourceId, $event->newPrimaryPaymentSourceId];
    });
    YiiEvent::on(LegacyWebhooks::class, LegacyWebhooks::EVENT_AFTER_PROCESS_WEBHOOK, function(WebhookEvent $event) use (&$received) {
        $received[] = $event->response;
    });

    event(new PrimaryPaymentSourceUpdated($customer, 1, 2));
    $webhookEvent = new WebhookProcessed(new Dummy());
    $webhookEvent->response = $response;
    event($webhookEvent);

    expect($received)->toBe([[$customer, 1, 2], $response]);
});
