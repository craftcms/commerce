<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Payment\Gateway;

use CraftCms\Cms\Component\TypeRegistry;
use CraftCms\Commerce\Payment\Gateway\Contracts\GatewayInterface;
use CraftCms\Commerce\Payment\Gateway\Types\Dummy;
use CraftCms\Commerce\Payment\Gateway\Types\Manual;
use Illuminate\Container\Attributes\Singleton;

/**
 * Registers gateway type classes.
 *
 * ```php
 * public function boot(GatewayTypes $gatewayTypes): void
 * {
 *     $gatewayTypes->register(MyGateway::class);
 * }
 * ```
 *
 * @extends TypeRegistry<GatewayInterface>
 */
#[Singleton]
class GatewayTypes extends TypeRegistry
{
    protected const ?string CONTRACT = GatewayInterface::class;

    protected const array DEFAULT_TYPES = [
        Dummy::class,
        Manual::class,
    ];
}
