<?php

namespace craft\commerce\services;

use CraftCms\Commerce\Services\ShippingZones as NewShippingZones;
use CraftCms\Commerce\Shipping\Models\ShippingAddressZone;
use Illuminate\Support\Collection;
use yii\base\Component;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\Services\ShippingZones::class)` instead.
 */
class ShippingZones extends Component
{
    private function impl(): NewShippingZones
    {
        return app(NewShippingZones::class);
    }

    /**
     * @return Collection<int, ShippingAddressZone>
     */
    public function getAllShippingZones(?int $storeId = null): Collection
    {
        return $this->impl()->getAllShippingZones($storeId);
    }

    public function getShippingZoneById(int $id, ?int $storeId = null): ?ShippingAddressZone
    {
        return $this->impl()->getShippingZoneById($id, $storeId);
    }

    public function saveShippingZone(ShippingAddressZone $model, bool $runValidation = true): bool
    {
        return $this->impl()->saveShippingZone($model, $runValidation);
    }

    public function deleteShippingZoneById(int $id): bool
    {
        return $this->impl()->deleteShippingZoneById($id);
    }
}
