<?php

declare(strict_types=1);

use CraftCms\Commerce\Payment\Events\PaymentCurrencyRateEvent;
use CraftCms\Commerce\Payment\Models\PaymentCurrency as PaymentCurrencyRecord;
use CraftCms\Commerce\Payment\PaymentCurrencies;
use CraftCms\Commerce\Tests\Support\PaymentCurrenciesFixture;
use Illuminate\Support\Facades\Event;
use Money\Currency;
use Money\Money;

test('getAllPaymentCurrencies includes the store default plus fixture currencies, resolvable by ISO', function() {
    PaymentCurrenciesFixture::seed();
    $pc = app(PaymentCurrencies::class);

    $eur = $pc->getPaymentCurrencyByIso('EUR');
    $aud = $pc->getPaymentCurrencyByIso('AUD');

    // Install's USD, plus 2 additional currencies in fixture data.
    expect($pc->getAllPaymentCurrencies())->toHaveCount(3);

    expect($eur)->not->toBeNull();
    expect($eur->iso)->toBe('EUR');
    expect($aud)->not->toBeNull();
    expect($aud->iso)->toBe('AUD');

    // Default install has a USD primary currency.
    expect($pc->getPrimaryPaymentCurrencyIso())->toBe('USD');
});

test('convert converts an amount from the primary currency to another by ISO code', function() {
    PaymentCurrenciesFixture::seed();
    $pc = app(PaymentCurrencies::class);

    expect($pc->convert(10, $pc->getPrimaryPaymentCurrencyIso()))->toBe(10.0);
    expect($pc->convert(10, 'EUR'))->toBe(5.0);
    expect($pc->convert(10, 'AUD'))->toBe(13.0);
});

test('convertCurrency converts between two currencies, normalizing through the primary', function() {
    PaymentCurrenciesFixture::seed();
    $pc = app(PaymentCurrencies::class);
    $primary = $pc->getPrimaryPaymentCurrencyIso();

    expect($pc->convertCurrency(20, 'EUR', $primary))->toBe(40.0);
    expect($pc->convertCurrency(40, $primary, 'EUR'))->toBe(20.0);

    expect($pc->convertCurrency(13, 'AUD', $primary))->toBe(10.0);
    expect($pc->convertCurrency(10, $primary, 'AUD'))->toBe(13.0);

    expect($pc->convertCurrency(13, 'AUD', 'EUR'))->toBe(5.0);
    expect($pc->convertCurrency(5, 'EUR', 'AUD'))->toBe(13.0);
});

test('convertCurrency throws for an unrecognized currency', function() {
    PaymentCurrenciesFixture::seed();

    expect(fn() => app(PaymentCurrencies::class)->convertCurrency(20, 'aaa', 'bbb'))->toThrow(RuntimeException::class);
});

test('convert throws for an unrecognized currency', function() {
    PaymentCurrenciesFixture::seed();

    expect(fn() => app(PaymentCurrencies::class)->convert(20, 'aaa'))->toThrow(RuntimeException::class);
});

test('getRateFor returns the raw rate when no event handler overrides it', function() {
    $fixture = PaymentCurrenciesFixture::seed();
    $pc = app(PaymentCurrencies::class);

    expect($pc->getRateFor($fixture->eur))->toBe(0.5);
});

test('getRateFor returns the event-overridden rate, leaving other currencies alone', function() {
    $fixture = PaymentCurrenciesFixture::seed();
    $pc = app(PaymentCurrencies::class);

    Event::listen(PaymentCurrencyRateEvent::class, function(PaymentCurrencyRateEvent $event) {
        if ($event->paymentCurrency->iso === 'EUR') {
            $event->rate = 0.25;
        }
    });

    expect($pc->getRateFor($fixture->eur))->toBe(0.25);
    expect($pc->getRateFor($fixture->aud))->toBe(1.3);
});

test('convertCurrency uses the event-overridden rate', function() {
    PaymentCurrenciesFixture::seed();
    $pc = app(PaymentCurrencies::class);

    Event::listen(PaymentCurrencyRateEvent::class, function(PaymentCurrencyRateEvent $event) {
        if ($event->paymentCurrency->iso === 'EUR') {
            $event->rate = 0.25;
        }
    });

    $converted = $pc->convertCurrency(40, $pc->getPrimaryPaymentCurrencyIso(), 'EUR');

    expect($converted)->toBe(10.0);
});

test('convertAmount uses the event-overridden rate', function() {
    PaymentCurrenciesFixture::seed();
    $pc = app(PaymentCurrencies::class);

    Event::listen(PaymentCurrencyRateEvent::class, function(PaymentCurrencyRateEvent $event) {
        if ($event->paymentCurrency->iso === 'EUR') {
            $event->rate = 0.25;
        }
    });

    $usd = new Money(4000, new Currency('USD'));
    $converted = $pc->convertAmount($usd, 'EUR');

    expect($converted->getCurrency()->getCode())->toBe('EUR');
    expect($converted->getAmount())->toBe('1000');
});

test('savePaymentCurrency persists the raw admin-entered rate, not an event-overridden rate', function() {
    $fixture = PaymentCurrenciesFixture::seed();
    $pc = app(PaymentCurrencies::class);

    Event::listen(PaymentCurrencyRateEvent::class, function(PaymentCurrencyRateEvent $event) {
        $event->rate = 999.0;
    });

    $eur = $fixture->eur;
    $eur->rate = 0.75;

    expect($pc->savePaymentCurrency($eur))->toBeTrue();

    $record = PaymentCurrencyRecord::find($eur->id);
    expect($record)->not->toBeNull();
    expect((float) $record->rate)->toBe(0.75);
});
