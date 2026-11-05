<?php

declare(strict_types=1);

use CraftCms\Cms\Support\Url;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Payment\Gateway\Gateways;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

beforeEach(function() {
    prioritizeCommerceRoutes();

    $this->user = User::find()->admin(true)->one();
    actingAs($this->user);

    // Installing Commerce creates a Dummy gateway.
    $this->gateway = app(Gateways::class)->getGatewayByHandle('dummy');
});

it('adds a payment source and makes it the primary one when isPrimaryPaymentSource is posted as a form value', function() {
    $paymentSourceId = postJson(Url::actionUrl('commerce/payment-sources/add'), [
        'gatewayId' => (string)$this->gateway->id,
        'description' => 'Test card',
        // A checkbox posts its value as a string.
        'isPrimaryPaymentSource' => '1',
        'paymentForm' => [
            'dummy' => [
                'firstName' => 'Test',
                'lastName' => 'Customer',
                'number' => '4242424242424242',
                'expiry' => '12 / 2030',
                'cvv' => '123',
            ],
        ],
    ])->assertOk()->json('paymentSource.id');

    expect($paymentSourceId)->toBeInt()
        ->and(User::find()->id($this->user->id)->one()->getPrimaryPaymentSourceId())->toBe($paymentSourceId);
});
