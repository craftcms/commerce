<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Override;

/**
 * @method static \Illuminate\Support\Collection getAllTaxZones(int|null $storeId = null)
 * @method static \CraftCms\Commerce\Tax\Data\TaxAddressZone|null getTaxZoneById(int $id, int|null $storeId = null)
 * @method static bool saveTaxZone(\CraftCms\Commerce\Tax\Data\TaxAddressZone $model, bool $runValidation = true)
 * @method static bool deleteTaxZoneById(int $id)
 *
 * @see \CraftCms\Commerce\Tax\TaxZones
 */
class TaxZones extends Facade
{
    #[Override]
    protected static function getFacadeAccessor(): string
    {
        return \CraftCms\Commerce\Tax\TaxZones::class;
    }
}
