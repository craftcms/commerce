<?php

declare(strict_types=1);

use CraftCms\Cms\Support\Facades\Users;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\LineItem\LineItems;
use CraftCms\Commerce\Payment\Gateway\Gateways;
use CraftCms\Commerce\Payment\Gateway\Types\Dummy;
use CraftCms\Commerce\Payment\Models\Transaction as TransactionRecord;
use CraftCms\Commerce\Payment\Transactions;
use CraftCms\Commerce\Tests\Support\OrdersFixture;

/**
 * Stubs a resolvable gateway for the given order, so `Transactions::createTransaction()` can
 * build a transaction against it without a real payment gateway configured in the store.
 */
function stubGatewayForOrder(Order $order): void
{
    $gateway = app(Gateways::class)->createGateway([
        'type' => Dummy::class,
        'name' => 'Dummy',
        'handle' => 'dummy',
        'isFrontendEnabled' => true,
    ]);
    $gateway->id = 1;
    $order->gatewayId = $gateway->id;

    $mock = Mockery::mock(Gateways::class)->makePartial();
    $mock->shouldReceive('getGatewayById')->andReturn($gateway);
    app()->instance(Gateways::class, $mock);
}

test('markAsComplete sets dateOrdered, isCompleted, and orderCompletedEmail', function() {
    $fixture = OrdersFixture::seed();
    $user = Users::ensureUserByEmail('test@newemailaddress.xyz');

    $order = new Order();
    $order->setCustomer($user);

    $lineItem = app(LineItems::class)->create($order, [
        'purchasableId' => $fixture->white->id,
        'qty' => 4,
        'note' => 'My note',
    ]);
    $order->setLineItems([$lineItem]);

    expect($order->dateOrdered)->toBeNull();
    expect($order->isCompleted)->toBeFalse();
    expect($order->orderCompletedEmail)->toBeNull();

    expect($order->markAsComplete())->toBeTrue();

    expect($order->dateOrdered)->toBeInstanceOf(DateTime::class);
    expect($order->isCompleted)->toBeTrue();
    expect($order->orderCompletedEmail)->toBe($user->email);
});

test('saveTransaction updates dateFirstPaid on first payment, and preserves it across a refund and a later repayment', function() {
    $fixture = OrdersFixture::seed();
    $order = $fixture->orders['completed-new'];
    stubGatewayForOrder($order);

    $transactions = app(Transactions::class);

    $purchase = $transactions->createTransaction($order, typeOverride: TransactionRecord::TYPE_PURCHASE);
    $purchase->status = TransactionRecord::STATUS_SUCCESS;
    $transactions->saveTransaction($purchase);

    $dateFirstPaid = $purchase->getOrder()->dateFirstPaid;
    $datePaid = $purchase->getOrder()->datePaid;

    expect($dateFirstPaid)->not->toBeNull();
    expect($datePaid)->not->toBeNull();

    // Refunding part of the payment clears datePaid (the order is no longer paid in full) but
    // must not disturb dateFirstPaid, which records when the order was first paid at all.
    $refund = $transactions->createTransaction(parentTransaction: $purchase, typeOverride: TransactionRecord::TYPE_REFUND);
    $refund->amount = 10;
    $refund->paymentAmount = 10;
    $refund->status = TransactionRecord::STATUS_SUCCESS;
    $transactions->saveTransaction($refund);

    $refundDateFirstPaid = $refund->getOrder()->dateFirstPaid;
    $refundDatePaid = $refund->getOrder()->datePaid;

    expect($refundDateFirstPaid->format('Y-m-d H:i:s'))->toBe($dateFirstPaid->format('Y-m-d H:i:s'));
    expect($refundDatePaid)->toBeNull();

    // A slight delay so the repayment's timestamp is distinguishable from the first payment's —
    // both dates are stored at second resolution.
    sleep(3);

    // Paying off the remaining balance updates datePaid again, but dateFirstPaid still doesn't move.
    $repayment = $transactions->createTransaction($order, typeOverride: TransactionRecord::TYPE_PURCHASE);
    $repayment->status = TransactionRecord::STATUS_SUCCESS;
    $transactions->saveTransaction($repayment);

    $nextDateFirstPaid = $repayment->getOrder()->dateFirstPaid;
    $nextDatePaid = $repayment->getOrder()->datePaid;

    expect($nextDateFirstPaid->format('Y-m-d H:i:s'))->toBe($dateFirstPaid->format('Y-m-d H:i:s'));
    expect($nextDatePaid->format('Y-m-d H:i:s'))->not->toBe($datePaid->format('Y-m-d H:i:s'));
    expect($nextDatePaid)->not->toBeNull();

    $transactions->deleteTransactionById($purchase->id);
    $transactions->deleteTransactionById($refund->id);
    $transactions->deleteTransactionById($repayment->id);
});
