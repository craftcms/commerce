<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\db\Table;
use craft\commerce\models\TaxAddressZone;
use craft\commerce\records\TaxZone as TaxZoneRecord;
use craft\db\Query;
use Throwable;
use yii\base\Component;
use yii\base\Exception;
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
     * @var TaxAddressZone[]
     */
    private array $_allZones = [];

    /**
     * Get all tax zones.
     *
     * @return TaxAddressZone[]
     */
    public function getAllTaxZones(): array
    {
        $rows = $this->_createQuery()->all();

        foreach ($rows as $row) {
            $this->_allZones[$row['id']] = new TaxAddressZone($row);
        }

        return $this->_allZones;
    }

    /**
     * Get a tax zone by its ID.
     */
    public function getTaxZoneById(int $id): ?TaxAddressZone
    {
        if (isset($this->_allZones[$id])) {
            return $this->_allZones[$id];
        }

        $result = $this->_createQuery()
            ->where(['id' => $id])
            ->one();

        if (!$result) {
            return null;
        }

        return $this->_allZones[$id] = new TaxAddressZone($result);
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
        $record->name = $model->name;
        $record->description = $model->description;
        $record->default = $model->default;
        $record->condition = $model->getCondition();

        $record->save();

        $model->id = $record->id;

        // If this was the default make all others not the default.
        if ($model->default) {
            TaxZoneRecord::updateAll(['default' => false], ['not', ['id' => $model->id]]);
        }

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
                'name'
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
