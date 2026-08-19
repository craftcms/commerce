<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers;

use CraftCms\Commerce\Payment\Gateway\Gateways;
use CraftCms\Commerce\Payment\Webhooks;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

readonly class WebhooksController
{
    public function processWebhook(Request $request, ?int $gatewayId = null): Response
    {
        $gatewayId ??= $request->input('gateway');

        abort_if(!$gatewayId, 400, 'Invalid gateway ID: ' . $gatewayId);

        $gateway = app(Gateways::class)->getGatewayById((int)$gatewayId);
        abort_if($gateway === null, 404, 'Gateway not found');

        /** @phpstan-ignore-next-line argument.type (legacy craft\commerce\base\Gateway implements GatewayInterface via the class_alias chain, which PHPStan can't trace) */
        return app(Webhooks::class)->processWebhook($gateway);
    }
}
