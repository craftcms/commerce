<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Override;

/**
 * @method static \Illuminate\Support\Collection getAllShippingZones(int|null $storeId = null)
 * @method static \CraftCms\Commerce\Shipping\Data\ShippingAddressZone|null getShippingZoneById(int $id, int|null $storeId = null)
 * @method static bool saveShippingZone(\CraftCms\Commerce\Shipping\Data\ShippingAddressZone $model, bool $runValidation = true)
 * @method static bool deleteShippingZoneById(int $id)
 *
 * @see \CraftCms\Commerce\Shipping\ShippingZones
 */
class ShippingZones extends Facade
{
    #[Override]
    protected static function getFacadeAccessor(): string
    {
        return \CraftCms\Commerce\Shipping\ShippingZones::class;
    }
}
