<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Override;

/**
 * @method static \Illuminate\Http\Response processWebhook(\CraftCms\Commerce\Payment\Gateway\Contracts\GatewayInterface $gateway)
 *
 * @see \CraftCms\Commerce\Payment\Webhooks
 */
class Webhooks extends Facade
{
    #[Override]
    protected static function getFacadeAccessor(): string
    {
        return \CraftCms\Commerce\Payment\Webhooks::class;
    }
}
