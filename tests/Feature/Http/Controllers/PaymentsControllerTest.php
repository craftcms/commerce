<?php

declare(strict_types=1);

use CraftCms\Cms\Support\Url;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Payment\Gateway\Gateways;
use CraftCms\Commerce\Payment\Gateway\Types\Dummy;
use CraftCms\Commerce\Tests\Support\OrdersFixture;
use Illuminate\Support\Facades\Crypt;

use function Pest\Laravel\post;

beforeEach(function() {
    prioritizeCommerceRoutes();

    $this->order = OrdersFixture::seed()->orders['completed-new'];

    // Installing Commerce creates a Dummy gateway.
    $this->gateway = app(Gateways::class)->getGatewayByHandle('dummy');
    expect($this->gateway)->toBeInstanceOf(Dummy::class);
});

function payForOrder(Order $order, int $gatewayId, array $params = []): Illuminate\Testing\TestResponse
{
    return post(Url::actionUrl('commerce/payments/pay'), [
        'number' => $order->number,
        'email' => $order->getEmail(),
        'gatewayId' => (string)$gatewayId,
        'paymentForm' => [
            'dummy' => [
                'firstName' => 'Emmett',
                'lastName' => 'Brown',
                'number' => '4242424242424242',
                'month' => '12',
                'year' => '2030',
                'cvv' => '123',
            ],
        ],
        ...$params,
    ]);
}

it('decrypts the hashed redirect and cancel URLs when paying', function() {
    payForOrder($this->order, $this->gateway->id, [
        'redirect' => Crypt::encrypt('shop/customer/order?number={number}'),
        'cancelUrl' => Crypt::encrypt('shop/checkout/payment'),
    ])->assertRedirect('shop/customer/order?number=' . $this->order->number);

    $order = Order::find()->id($this->order->id)->one();

    expect($order->returnUrl)->toBe('shop/customer/order?number=' . $this->order->number)
        ->and($order->cancelUrl)->toBe('shop/checkout/payment');
});

it('rejects a redirect URL that wasn’t hashed', function() {
    payForOrder($this->order, $this->gateway->id, [
        'redirect' => 'https://example.com',
    ])->assertBadRequest();
});
