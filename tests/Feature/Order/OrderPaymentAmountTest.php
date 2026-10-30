<?php

declare(strict_types=1);

use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\LineItem\Data\LineItem;
use CraftCms\Commerce\Payment\Data\Transaction;
use CraftCms\Commerce\Payment\Models\Transaction as TransactionRecord;
use CraftCms\Commerce\Tests\Support\PaymentCurrenciesFixture;

test('getOutstandingBalance, getPaymentAmount and setPaymentAmount track transactions against the order total', function() {
    $order = new Order();
    $order->id = 1000;

    $lineItem = new LineItem();
    $lineItem->price = 10;
    $lineItem->qty = 2;
    $order->setLineItems([$lineItem]);

    // There's an amount to owe on this order.
    expect($order->hasOutstandingBalance())->toBeTrue();

    // Amount owed is the payment amount.
    expect($order->getPaymentAmount())->toBe($order->getOutstandingBalance());

    // The setter/getter round-trip.
    $order->setPaymentAmount(10);
    expect($order->getPaymentAmount())->toBe(10.0);

    // Add a $12 successful transaction to the order.
    $transaction1 = new Transaction();
    $transaction1->amount = 12;
    $transaction1->type = TransactionRecord::TYPE_PURCHASE;
    $transaction1->status = TransactionRecord::STATUS_SUCCESS;
    $order->setTransactions([$transaction1]);

    expect($order->getOutstandingBalance())->toBe(8.0);

    // Add a $2 successful refund transaction to the order.
    $transaction2 = new Transaction();
    $transaction2->amount = 2;
    $transaction2->type = TransactionRecord::TYPE_REFUND;
    $transaction2->status = TransactionRecord::STATUS_SUCCESS;
    $order->setTransactions([$transaction1, $transaction2]);

    // Paid $12 and refunded $2, order price was $20, so the outstanding amount is now $10.
    expect($order->getOutstandingBalance())->toBe(10.0);

    // The payment amount set earlier is still 10.
    expect($order->getPaymentAmount())->toBe(10.0);

    // Setting a payment amount in excess of the outstanding balance is ignored, and the
    // outstanding balance is returned instead.
    $order->setPaymentAmount(1000);
    expect($order->getPaymentAmount())->toBe($order->getOutstandingBalance());
});

test('isPaymentAmountPartial compares the payment amount against the outstanding balance across currencies', function(array $lineItems, ?float $paymentAmount, string $paymentCurrency, bool $isPartial) {
    PaymentCurrenciesFixture::seed();

    $order = new Order();
    $order->setLineItems(array_map(fn(array $attributes) => new LineItem($attributes), $lineItems));
    $order->setPaymentCurrency($paymentCurrency);

    if ($paymentAmount !== null) {
        $order->setPaymentAmount($paymentAmount);
    }

    expect($order->isPaymentAmountPartial())->toBe($isPartial);
})->with([
    'partial-payment' => [
        ['first' => ['qty' => 1, 'price' => 10], 'second' => ['qty' => 1, 'price' => 20]],
        10,
        'AUD',
        true,
    ],
    'full-payment-specified' => [
        ['first' => ['qty' => 1, 'price' => 10], 'second' => ['qty' => 1, 'price' => 7.75]],
        23.08,
        'AUD',
        false,
    ],
    'currency-specified-but-no-amount' => [
        ['first' => ['qty' => 1, 'price' => 10], 'second' => ['qty' => 1, 'price' => 20]],
        null,
        'AUD',
        false,
    ],
]);
