<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Payment\Gateway;

use craft\commerce\gateways\Dummy;
use craft\commerce\gateways\Manual;
use CraftCms\Cms\Component\TypeRegistry;
use CraftCms\Commerce\Payment\Gateway\Contracts\GatewayInterface;
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

    /** @phpstan-ignore-next-line classConstant.value (Dummy/Manual implement GatewayInterface via the legacy craft\commerce\base\Gateway class_alias chain, which PHPStan can't trace) */
    protected const array DEFAULT_TYPES = [
        Dummy::class,
        Manual::class,
    ];
}
