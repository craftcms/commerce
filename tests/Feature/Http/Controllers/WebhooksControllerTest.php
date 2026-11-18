<?php

declare(strict_types=1);

use CraftCms\Cms\Support\Url;
use CraftCms\Commerce\Payment\Gateway\Gateways;

beforeEach(function() {
    prioritizeCommerceRoutes();
});

it('accepts webhooks sent as GET or POST requests', function(string $method) {
    // Installing Commerce creates a Dummy gateway, which doesn’t support webhooks.
    $gatewayId = app(Gateways::class)->getGatewayByHandle('dummy')->id;

    $this->call($method, Url::actionUrl('commerce/webhooks/process-webhook', ['gateway' => $gatewayId]))->assertBadRequest();
    $this->call($method, "/commerce/webhooks/process-webhook/gateway/$gatewayId")->assertBadRequest();
})->with(['GET', 'POST']);
