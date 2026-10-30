<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Tax;

use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Store\Stores;
use CraftCms\Commerce\Tax\Data\TaxRate;
use CraftCms\Commerce\Tax\Models\TaxRate as TaxRateRecord;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use function CraftCms\Cms\t;

#[Singleton]
class TaxRates
{
    /** @var array<int, Collection<int, TaxRate>>|null */
    private ?array $allTaxRates = null;

    /**
     * @return Collection<int, TaxRate>
     */
    public function getAllTaxRates(?int $storeId = null): Collection
    {
        $storeId ??= $this->currentStoreId();

        if ($this->allTaxRates === null || !isset($this->allTaxRates[$storeId])) {
            $rows = $this->query()->where('storeId', $storeId)->get()->all();

            $this->allTaxRates ??= [];

            foreach ($rows as $row) {
                $taxRate = new TaxRate((array) $row);
                $this->allTaxRates[$taxRate->storeId] ??= collect();
                $this->allTaxRates[$taxRate->storeId]->push($taxRate);
            }
        }

        return $this->allTaxRates[$storeId] ?? collect();
    }

    /**
     * @return Collection<int, TaxRate>
     */
    public function getAllEnabledTaxRates(?int $storeId = null): Collection
    {
        return $this->getAllTaxRates($storeId)->where('enabled', true);
    }

    /**
     * @return Collection<int, TaxRate>
     */
    public function getTaxRatesByTaxZoneId(int $taxZoneId, ?int $storeId = null): Collection
    {
        return $this->getAllTaxRates($storeId)->where('taxZoneId', $taxZoneId);
    }

    public function getTaxRateById(int $id, ?int $storeId = null): ?TaxRate
    {
        return $this->getAllTaxRates($storeId)->firstWhere('id', $id);
    }

    public function saveTaxRate(TaxRate $model, bool $runValidation = true): bool
    {
        if ($model->id) {
            $record = TaxRateRecord::find($model->id);
            if (!$record) {
                throw new RuntimeException(t('No tax rate exists with the ID “{id}”', ['id' => $model->id], category: 'commerce'));
            }
        } else {
            $record = new TaxRateRecord();
        }

        if ($runValidation && !$model->validate()) {
            return false;
        }

        $record->name = $model->name;
        $record->code = $model->code;
        $record->rate = $model->rate;
        $record->storeId = $model->storeId;

        // if not an included tax, then can not be removed.
        $record->include = $model->include;
        $record->isVat = $model->hasTaxIdValidators();
        $record->removeIncluded = !$record->include ? false : $model->removeIncluded;
        $record->removeVatIncluded = (!$record->include || !$record->isVat) ? false : $model->removeVatIncluded;
        $record->taxable = $model->taxable;
        $record->taxCategoryId = $model->taxCategoryId;
        $record->taxZoneId = $model->taxZoneId ?: null;
        $record->isEverywhere = $model->getIsEverywhere();
        $record->enabled = $model->enabled;
        $record->taxIdValidators = $model->taxIdValidators;

        if (!$record->isEverywhere && $record->taxZoneId) {
            $taxZone = app(TaxZones::class)->getTaxZoneById($record->taxZoneId, $record->storeId);

            if (!$taxZone) {
                throw new RuntimeException(t('No tax zone exists with the ID “{id}”', ['id' => $record->taxZoneId], category: 'commerce'));
            }

            if ($record->removeIncluded && !$taxZone->default) {
                $model->addError('removeIncluded', t('Removable included tax rates are only allowed for the default tax zone.', category: 'commerce'));

                return false;
            }
        }

        $record->save();

        $model->id = $record->id;
        $this->clearCache();

        return true;
    }

    public function deleteTaxRateById(int $id): bool
    {
        $record = TaxRateRecord::find($id);

        if (!$record) {
            return false;
        }

        $result = (bool) $record->delete();
        if ($result) {
            $this->clearCache();
        }

        return $result;
    }

    private function clearCache(): void
    {
        $this->allTaxRates = null;
    }

    private function query(): \Illuminate\Database\Query\Builder
    {
        return DB::table(Table::TAXRATES)
            ->select([
                'code',
                'dateCreated',
                'dateUpdated',
                'enabled',
                'id',
                'include',
                'name',
                'rate',
                'removeIncluded',
                'removeVatIncluded',
                'storeId',
                'taxable',
                'taxCategoryId',
                'taxIdValidators',
                'taxZoneId',
            ])
            ->orderByDesc('include')
            ->orderByDesc('isVat');
    }

    private function currentStoreId(): int
    {
        return app(Stores::class)->getCurrentStore()->id;
    }
}
