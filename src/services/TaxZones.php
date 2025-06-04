<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\db\Table;
use craft\commerce\errors\StoreNotFoundException;
use craft\commerce\models\TaxAddressZone;
use craft\commerce\Plugin;
use craft\commerce\records\TaxZone as TaxZoneRecord;
use craft\db\Query;
use Illuminate\Support\Collection;
use Throwable;
use yii\base\Component;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\db\StaleObjectException;

/**
 * Tax zone service.
 *
 * @property TaxAddressZone[]|array $allTaxZones
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class TaxZones extends Component
{
    /**
     * @var Collection<TaxAddressZone>[]
     */
    private ?array $_allZones = null;

    /**
     * Get all tax zones.
     *
     * @param int|null $storeId
     * @return Collection
     * @throws StoreNotFoundException
     * @throws InvalidConfigException
     */
    public function getAllTaxZones(?int $storeId = null): Collection
    {
        $storeId ??= Plugin::getInstance()->getStores()->getCurrentStore()->id;

        if ($this->_allZones === null || !isset($this->_allZones[$storeId])) {
            $results = $this->_createQuery()
                ->where(['storeId' => $storeId])
                ->all();

            if ($this->_allZones === null) {
                $this->_allZones = [];
            }

            foreach ($results as $result) {
                $taxRate = Craft::createObject([
                    'class' => TaxAddressZone::class,
                    'attributes' => $result,
                ]);

                if (!isset($this->_allZones[$taxRate->storeId])) {
                    $this->_allZones[$taxRate->storeId] = collect();
                }

                $this->_allZones[$taxRate->storeId]->push($taxRate);
            }
        }

        return $this->_allZones[$storeId] ?? collect();
    }

    /**
     * Get a tax zone by its ID.
     */
    public function getTaxZoneById(int $id, ?int $storeId = null): ?TaxAddressZone
    {
        return $this->getAllTaxZones($storeId)->firstWhere('id', $id);
    }

    /**
     * Save a tax zone.
     *
     * @param bool $runValidation should we validate this zone before saving
     * @throws \Exception
     * @throws Exception
     */
    public function saveTaxZone(TaxAddressZone $model, bool $runValidation = true): bool
    {
        if ($model->id) {
            $record = TaxZoneRecord::findOne($model->id);

            if (!$record) {
                throw new Exception(Craft::t('commerce', 'No tax zone exists with the ID “{id}”', ['id' => $model->id]));
            }
        } else {
            $record = new TaxZoneRecord();
        }

        if ($runValidation && !$model->validate()) {
            Craft::info('Tax zone not saved due to validation error.', __METHOD__);

            return false;
        }

        //setting attributes
        $record->storeId = $model->storeId;
        $record->name = $model->name;
        $record->description = $model->description;
        $record->default = $model->default;
        $record->condition = $model->getCondition()->getConfig();

        $record->save();

        $model->id = $record->id;

        // If this was the default make all others not the default.
        if ($model->default) {
            TaxZoneRecord::updateAll(
                ['default' => false],
                ['and', ['not', ['id' => $model->id]], ['storeId' => $model->storeId]]);
        }

        $this->_clearCaches();

        return true;
    }

    /**
     * @throws Throwable
     * @throws StaleObjectException
     */
    public function deleteTaxZoneById(int $id): bool
    {
        $record = TaxZoneRecord::findOne($id);

        if ($record) {
            $result = (bool)$record->delete();
            if ($result) {
                $this->_clearCaches();
            }

            return $result;
        }

        return false;
    }

    /**
     * Returns a Query object prepped for retrieving tax zones.
     */
    private function _createQuery(): Query
    {
        return (new Query())
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
            ->orderBy('name')
            ->from([Table::TAXZONES]);
    }

    /**
     * Clear memoization.
     *
     * @since 3.2.5
     */
    private function _clearCaches(): void
    {
        $this->_allZones = [];
    }
}
