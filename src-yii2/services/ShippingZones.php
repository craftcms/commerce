<?php

namespace craft\commerce\services;

use CraftCms\Commerce\Shipping\Models\ShippingAddressZone;
use Illuminate\Support\Collection;
use yii\base\Component;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\Services\ShippingZones::class)` instead.
 */
class ShippingZones extends Component
{
    /**
     * @return Collection<int, ShippingAddressZone>
     */
    public function getAllShippingZones(?int $storeId = null): Collection
    {
        return app(\CraftCms\Commerce\Services\ShippingZones::class)->getAllShippingZones($storeId);
    }

    public function getShippingZoneById(int $id, ?int $storeId = null): ?ShippingAddressZone
    {
        return app(\CraftCms\Commerce\Services\ShippingZones::class)->getShippingZoneById($id, $storeId);
    }

    public function saveShippingZone(ShippingAddressZone $model, bool $runValidation = true): bool
    {
        return app(\CraftCms\Commerce\Services\ShippingZones::class)->saveShippingZone($model, $runValidation);
    }

    public function deleteShippingZoneById(int $id): bool
    {
        return app(\CraftCms\Commerce\Services\ShippingZones::class)->deleteShippingZoneById($id);
    }
}
