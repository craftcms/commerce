<?php

namespace craft\commerce\services;

use CraftCms\Commerce\Tax\Data\TaxRate;
use Illuminate\Support\Collection;
use yii\base\Component;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\Tax\TaxRates::class)` instead.
 */
class TaxRates extends Component
{
    /**
     * @return Collection<TaxRate>
     */
    public function getAllTaxRates(?int $storeId = null): Collection
    {
        return app(\CraftCms\Commerce\Tax\TaxRates::class)->getAllTaxRates($storeId);
    }

    /**
     * @return Collection<TaxRate>
     */
    public function getAllEnabledTaxRates(?int $storeId = null): Collection
    {
        return app(\CraftCms\Commerce\Tax\TaxRates::class)->getAllEnabledTaxRates($storeId);
    }

    /**
     * @return Collection<TaxRate>
     */
    public function getTaxRatesByTaxZoneId(int $taxZoneId, ?int $storeId = null): Collection
    {
        return app(\CraftCms\Commerce\Tax\TaxRates::class)->getTaxRatesByTaxZoneId($taxZoneId, $storeId);
    }

    public function getTaxRateById(int $id, ?int $storeId = null): ?TaxRate
    {
        return app(\CraftCms\Commerce\Tax\TaxRates::class)->getTaxRateById($id, $storeId);
    }

    public function saveTaxRate(TaxRate $model, bool $runValidation = true): bool
    {
        return app(\CraftCms\Commerce\Tax\TaxRates::class)->saveTaxRate($model, $runValidation);
    }

    public function deleteTaxRateById(int $id): bool
    {
        return app(\CraftCms\Commerce\Tax\TaxRates::class)->deleteTaxRateById($id);
    }
}
