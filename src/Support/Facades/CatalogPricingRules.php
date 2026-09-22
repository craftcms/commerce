<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Override;

/**
 * @see \CraftCms\Commerce\CatalogPricing\CatalogPricingRules
 */
class CatalogPricingRules extends Facade
{
    #[Override]
    protected static function getFacadeAccessor(): string
    {
        return \CraftCms\Commerce\CatalogPricing\CatalogPricingRules::class;
    }
}
