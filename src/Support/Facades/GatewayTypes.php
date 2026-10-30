<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Override;

/**
 * @method static void defer(\Closure $callback)
 * @method static void register(string ...$types)
 * @method static void remove(string ...$types)
 * @method static \Illuminate\Support\Collection types()
 *
 * @see \CraftCms\Commerce\Payment\Gateway\GatewayTypes
 */
class GatewayTypes extends Facade
{
    #[Override]
    protected static function getFacadeAccessor(): string
    {
        return \CraftCms\Commerce\Payment\Gateway\GatewayTypes::class;
    }
}
