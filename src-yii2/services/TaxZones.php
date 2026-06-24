<?php

namespace craft\commerce\services;

use CraftCms\Commerce\Services\TaxZones as NewTaxZones;
use CraftCms\Commerce\Tax\Models\TaxAddressZone;
use Illuminate\Support\Collection;
use yii\base\Component;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\Services\TaxZones::class)` instead.
 */
class TaxZones extends Component
{
    private function impl(): NewTaxZones
    {
        return app(NewTaxZones::class);
    }

    /**
     * @return Collection<int, TaxAddressZone>
     */
    public function getAllTaxZones(?int $storeId = null): Collection
    {
        return $this->impl()->getAllTaxZones($storeId);
    }

    public function getTaxZoneById(int $id, ?int $storeId = null): ?TaxAddressZone
    {
        return $this->impl()->getTaxZoneById($id, $storeId);
    }

    public function saveTaxZone(TaxAddressZone $model, bool $runValidation = true): bool
    {
        return $this->impl()->saveTaxZone($model, $runValidation);
    }

    public function deleteTaxZoneById(int $id): bool
    {
        return $this->impl()->deleteTaxZoneById($id);
    }
}
