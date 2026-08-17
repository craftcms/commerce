<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers;

use craft\commerce\Plugin;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

readonly class WebhooksController
{
    public function processWebhook(Request $request, ?int $gatewayId = null): Response
    {
        $gatewayId ??= $request->input('gateway');

        abort_if(!$gatewayId, 400, 'Invalid gateway ID: ' . $gatewayId);

        $gateway = Plugin::getInstance()->getGateways()->getGatewayById((int)$gatewayId);
        abort_if($gateway === null, 404, 'Gateway not found');

        return Plugin::getInstance()->getWebhooks()->processWebhook($gateway);
    }
}
