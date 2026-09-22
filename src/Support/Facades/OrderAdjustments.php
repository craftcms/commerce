<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Override;

/**
 * @see \CraftCms\Commerce\Order\OrderAdjustments
 */
class OrderAdjustments extends Facade
{
    #[Override]
    protected static function getFacadeAccessor(): string
    {
        return \CraftCms\Commerce\Order\OrderAdjustments::class;
    }
}
