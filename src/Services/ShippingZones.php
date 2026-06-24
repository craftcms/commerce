<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Services;

use craft\commerce\Plugin;
use craft\commerce\records\ShippingZone as ShippingZoneRecord;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Shipping\Models\ShippingAddressZone;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use function CraftCms\Cms\t;

#[Singleton]
class ShippingZones
{
    /** @var array<int, Collection<int, ShippingAddressZone>>|null */
    private ?array $allZones = null;

    /**
     * @return Collection<int, ShippingAddressZone>
     */
    public function getAllShippingZones(?int $storeId = null): Collection
    {
        $storeId ??= $this->currentStoreId();

        if ($this->allZones === null || !isset($this->allZones[$storeId])) {
            $rows = $this->query()->where('storeId', $storeId)->get()->all();

            $this->allZones ??= [];

            foreach ($rows as $row) {
                $zone = new ShippingAddressZone((array) $row);
                $this->allZones[$zone->storeId] ??= collect();
                $this->allZones[$zone->storeId]->push($zone);
            }
        }

        return $this->allZones[$storeId] ?? collect();
    }

    public function getShippingZoneById(int $id, ?int $storeId = null): ?ShippingAddressZone
    {
        return $this->getAllShippingZones($storeId)->firstWhere('id', $id);
    }

    public function saveShippingZone(ShippingAddressZone $model, bool $runValidation = true): bool
    {
        if ($model->id) {
            /** @phpstan-ignore-next-line */
            $record = ShippingZoneRecord::findOne($model->id);
            if (!$record) {
                throw new \RuntimeException(t('No shipping zone exists with the ID “{id}”', ['id' => $model->id], category: 'commerce'));
            }
        } else {
            $record = new ShippingZoneRecord();
        }

        if ($runValidation && !$model->validate()) {
            return false;
        }

        $record->name = $model->name;
        $record->storeId = $model->storeId;
        $record->description = $model->description;
        /** @phpstan-ignore-next-line */
        $record->condition = $model->getCondition()->getConfig();
        $this->clearCaches();

        $record->save();
        $model->id = $record->id;

        return true;
    }

    public function deleteShippingZoneById(int $id): bool
    {
        /** @phpstan-ignore-next-line */
        $record = ShippingZoneRecord::findOne($id);

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
        return DB::table(Table::SHIPPINGZONES)
            ->select([
                'condition',
                'dateCreated',
                'dateUpdated',
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
        return Plugin::getInstance()->getStores()->getCurrentStore()->id;
    }
}
