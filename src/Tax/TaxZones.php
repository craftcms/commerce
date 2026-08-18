<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Tax;

use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Store\Stores;
use CraftCms\Commerce\Tax\Models\TaxAddressZone;
use CraftCms\Commerce\Tax\Records\TaxZone as TaxZoneRecord;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use function CraftCms\Cms\t;

#[Singleton]
class TaxZones
{
    /** @var array<int, Collection<int, TaxAddressZone>>|null */
    private ?array $allZones = null;

    /**
     * @return Collection<int, TaxAddressZone>
     */
    public function getAllTaxZones(?int $storeId = null): Collection
    {
        $storeId ??= $this->currentStoreId();

        if ($this->allZones === null || !isset($this->allZones[$storeId])) {
            $rows = $this->query()->where('storeId', $storeId)->get()->all();

            $this->allZones ??= [];

            foreach ($rows as $row) {
                $zone = new TaxAddressZone((array) $row);
                $this->allZones[$zone->storeId] ??= collect();
                $this->allZones[$zone->storeId]->push($zone);
            }
        }

        return $this->allZones[$storeId] ?? collect();
    }

    public function getTaxZoneById(int $id, ?int $storeId = null): ?TaxAddressZone
    {
        return $this->getAllTaxZones($storeId)->firstWhere('id', $id);
    }

    public function saveTaxZone(TaxAddressZone $model, bool $runValidation = true): bool
    {
        if ($model->id) {
            $record = TaxZoneRecord::find($model->id);
            if (!$record) {
                throw new \RuntimeException(t('No tax zone exists with the ID “{id}”', ['id' => $model->id], category: 'commerce'));
            }
        } else {
            $record = new TaxZoneRecord();
        }

        if ($runValidation && !$model->validate()) {
            return false;
        }

        $record->storeId = $model->storeId;
        $record->name = $model->name;
        $record->description = $model->description;
        $record->default = $model->default;
        /** @phpstan-ignore-next-line */
        $record->condition = $model->getCondition()->getConfig();

        $record->save();

        $model->id = $record->id;

        // If this was the default, clear default on all others in the same store.
        if ($model->default) {
            TaxZoneRecord::where('id', '!=', $model->id)
                ->where('storeId', $model->storeId)
                ->update(['default' => false]);
        }

        $this->clearCaches();

        return true;
    }

    public function deleteTaxZoneById(int $id): bool
    {
        $record = TaxZoneRecord::find($id);

        if (!$record) {
            return false;
        }

        $result = (bool) $record->delete();
        if ($result) {
            $this->clearCaches();
        }

        return $result;
    }

    private function clearCaches(): void
    {
        $this->allZones = [];
    }

    private function query(): \Illuminate\Database\Query\Builder
    {
        return DB::table(Table::TAXZONES)
            ->select([
                'condition',
                'dateCreated',
                'dateUpdated',
                'default',
                'description',
                'id',
                'name',
                'storeId',
            ])
            ->orderBy('name');
    }

    private function currentStoreId(): int
    {
        /** @phpstan-ignore-next-line */
        return app(Stores::class)->getCurrentStore()->id;
    }
}
