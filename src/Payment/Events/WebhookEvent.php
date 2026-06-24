<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Payment\Events;

use CraftCms\Commerce\Payment\Gateway\Contracts\GatewayInterface;
use Illuminate\Http\Response;

class WebhookEvent
{
    public GatewayInterface $gateway;
    public Response $response;
}
