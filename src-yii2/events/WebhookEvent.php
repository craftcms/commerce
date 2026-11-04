<?php

namespace craft\commerce\events;

use CraftCms\Commerce\Payment\Gateway\Contracts\GatewayInterface;
use Illuminate\Http\Response;
use yii\base\Event;

/**
 * @deprecated 6.0.0
 */
class WebhookEvent extends Event
{
    /**
     * @var GatewayInterface The gateway processing the webhook
     */
    public GatewayInterface $gateway;

    /**
     * @var Response|null The webhook response
     */
    public ?Response $response = null;
}
