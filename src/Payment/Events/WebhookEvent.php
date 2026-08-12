<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Payment\Events;

use CraftCms\Commerce\Payment\Gateway\Contracts\GatewayInterface;
use Illuminate\Http\Response;
use yii\web\Response as YiiResponse;

class WebhookEvent
{
    /**
     * The webhook response. Still a {@see YiiResponse} in practice until
     * {@see WebhooksController} and the gateway request/response pipeline it
     * runs on are migrated off the legacy Yii2 controller system.
     */
    public YiiResponse|Response $response;

    public function __construct(
        public GatewayInterface $gateway,
    ) {}
}
