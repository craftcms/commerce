<?php

declare(strict_types=1);

use Carbon\Carbon;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\Models\Order as OrderRecord;
use CraftCms\Commerce\Payment\Gateway\Gateways;
use CraftCms\Commerce\Payment\Gateway\Types\Dummy;
use CraftCms\Commerce\Payment\Models\Transaction as TransactionRecord;
use CraftCms\Commerce\Payment\Transactions;
use CraftCms\Commerce\Tests\Support\OrdersFixture;

test('email() matches an order\'s customer email address, case-insensitively', function(string $email, int $count) {
    OrdersFixture::seed();

    $orderQuery = Order::find()->email($email);

    expect($orderQuery->all())->toHaveCount($count);
})->with([
    'normal' => ['customer1@crafttest.com', 3],
    'case-insensitive' => ['CuStOmEr1@crafttest.com', 3],
    'no-results' => ['null@craftcms.com', 0],
]);

test('couponCode() matches case-insensitively and supports :empty:/:notempty:', function(?string $couponCode, int $count) {
    $fixture = OrdersFixture::seed();
    $order = $fixture->orders['completed-new'];

    // Temporarily add a coupon code to an order, bypassing the order element entirely so no
    // discount needs to exist for the code to be considered valid.
    OrderRecord::query()->where('id', $order->id)->update(['couponCode' => 'foo']);

    $orderQuery = Order::find()->couponCode($couponCode);

    expect($orderQuery->all())->toHaveCount($count);

    OrderRecord::query()->where('id', $order->id)->update(['couponCode' => null]);
})->with([
    'normal' => ['foo', 1],
    'case-insensitive' => ['fOo', 1],
    'using-null' => [null, 3],
    'empty-code' => [':empty:', 2],
    'not-empty-code' => [':notempty:', 1],
    'no-results' => ['nope', 0],
]);

test('shippingMethodHandle() matches by string, negated string, array, and negated array', function(mixed $handle, int $count) {
    OrdersFixture::seed();

    $orderQuery = Order::find()->isCompleted()->shippingMethodHandle($handle);

    expect($orderQuery->all())->toHaveCount($count);
})->with([
    'queryShippingByString' => ['usShipping', 1],
    'queryShippingByNotString' => ['not usShipping', 2],
    'queryShippingByArray' => [['usShipping'], 1],
    'queryShippingByNotArray' => [['not', 'usShipping'], 2],
]);

test('datePaid() and dateFirstPaid() match orders paid within a date range', function() {
    OrdersFixture::seed();

    // Pay one of the completed orders so there's a datePaid/dateFirstPaid to filter on.
    $completedOrder = Order::find()->isCompleted()->one();

    $gateway = app(Gateways::class)->createGateway([
        'type' => Dummy::class,
        'name' => 'Dummy',
        'handle' => 'dummy',
        'isFrontendEnabled' => true,
    ]);
    $gateway->id = 1;
    $completedOrder->gatewayId = $gateway->id;

    $mock = Mockery::mock(Gateways::class)->makePartial();
    $mock->shouldReceive('getGatewayById')->andReturn($gateway);
    app()->instance(Gateways::class, $mock);

    $transactions = app(Transactions::class);
    $transaction = $transactions->createTransaction($completedOrder, typeOverride: TransactionRecord::TYPE_PURCHASE);
    $transaction->status = TransactionRecord::STATUS_SUCCESS;
    $transactions->saveTransaction($transaction);

    $paidOrder = Order::find()->id($completedOrder->id)->one();

    // Build the query bounds from the order's own persisted (and re-hydrated) datePaid/dateFirstPaid,
    // rather than the wall clock, so the assertion doesn't race against exactly when the payment
    // above was recorded.
    foreach (['datePaid' => $paidOrder->datePaid, 'dateFirstPaid' => $paidOrder->dateFirstPaid] as $property => $paidAt) {
        $paidAt = Carbon::instance($paidAt);

        expect(Order::find()->{$property}('>= ' . $paidAt->toAtomString())->all())->toHaveCount(1);
        expect(Order::find()->{$property}([
            '< ' . $paidAt->clone()->addWeek()->toAtomString(),
            '> ' . $paidAt->clone()->subWeek()->toAtomString(),
        ])->all())->toHaveCount(1);
        expect(Order::find()->{$property}('< ' . $paidAt->clone()->subWeek()->toAtomString())->all())->toHaveCount(0);
    }

    $transactions->deleteTransactionById($transaction->id);
});
