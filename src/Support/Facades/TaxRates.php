<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Override;

/**
 * @method static \Illuminate\Support\Collection getAllTaxRates(int|null $storeId = null)
 * @method static \Illuminate\Support\Collection getAllEnabledTaxRates(int|null $storeId = null)
 * @method static \Illuminate\Support\Collection getTaxRatesByTaxZoneId(int $taxZoneId, int|null $storeId = null)
 * @method static \CraftCms\Commerce\Tax\Data\TaxRate|null getTaxRateById(int $id, int|null $storeId = null)
 * @method static bool saveTaxRate(\CraftCms\Commerce\Tax\Data\TaxRate $model, bool $runValidation = true)
 * @method static bool deleteTaxRateById(int $id)
 *
 * @see \CraftCms\Commerce\Tax\TaxRates
 */
class TaxRates extends Facade
{
    #[Override]
    protected static function getFacadeAccessor(): string
    {
        return \CraftCms\Commerce\Tax\TaxRates::class;
    }
}
