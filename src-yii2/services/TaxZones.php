<?php

namespace craft\commerce\services;

use CraftCms\Commerce\Tax\Models\TaxAddressZone;
use Illuminate\Support\Collection;
use yii\base\Component;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\Tax\TaxZones::class)` instead.
 */
class TaxZones extends Component
{
    /**
     * @return Collection<int, TaxAddressZone>
     */
    public function getAllTaxZones(?int $storeId = null): Collection
    {
        return app(\CraftCms\Commerce\Tax\TaxZones::class)->getAllTaxZones($storeId);
    }

    public function getTaxZoneById(int $id, ?int $storeId = null): ?TaxAddressZone
    {
        return app(\CraftCms\Commerce\Tax\TaxZones::class)->getTaxZoneById($id, $storeId);
    }

    public function saveTaxZone(TaxAddressZone $model, bool $runValidation = true): bool
    {
        return app(\CraftCms\Commerce\Tax\TaxZones::class)->saveTaxZone($model, $runValidation);
    }

    public function deleteTaxZoneById(int $id): bool
    {
        return app(\CraftCms\Commerce\Tax\TaxZones::class)->deleteTaxZoneById($id);
    }
}
