<?php

declare(strict_types=1);

use CraftCms\Cms\Support\Json;
use CraftCms\Commerce\Payment\Gateway\Gateways;
use CraftCms\Commerce\Payment\Models\Transaction as TransactionRecord;
use CraftCms\Commerce\Payment\Transactions;
use CraftCms\Commerce\Tests\Support\OrdersFixture;

test('saveTransaction stores array gateway responses as JSON', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    // Installing Commerce creates a Dummy gateway.
    $order->gatewayId = app(Gateways::class)->getGatewayByHandle('dummy')->id;

    $transactions = app(Transactions::class);
    $transaction = $transactions->createTransaction($order, typeOverride: TransactionRecord::TYPE_PURCHASE);
    $transaction->status = TransactionRecord::STATUS_FAILED;
    $transaction->response = ['id' => 'ch_123', 'outcome' => ['type' => 'issuer_declined', 'risk_score' => 12]];

    expect($transactions->saveTransaction($transaction))->toBeTrue();

    $saved = $transactions->getTransactionById($transaction->id);
    expect(Json::decode($saved->response))->toBe(['id' => 'ch_123', 'outcome' => ['type' => 'issuer_declined', 'risk_score' => 12]]);
});
