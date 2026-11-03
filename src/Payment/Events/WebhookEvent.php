<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Payment\Events;

use CraftCms\Commerce\Payment\Gateway\Contracts\GatewayInterface;
use Illuminate\Http\Response;

abstract class WebhookEvent
{
    public Response $response;

    public function __construct(
        public GatewayInterface $gateway,
    ) {
    }
}
