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
use craft\commerce\Plugin;
use craft\commerce\records\Country as CountryRecord;
use craft\commerce\records\State as StateRecord;
use craft\commerce\records\TaxZone;
use craft\commerce\records\TaxZone as TaxZoneRecord;
use craft\commerce\records\TaxZoneCountry as TaxZoneCountryRecord;
use craft\commerce\records\TaxZoneState as TaxZoneStateRecord;
use craft\db\Query;
use yii\base\Component;
use yii\base\Exception;
use yii\caching\TagDependency;

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
     * @var bool
     */
    private $_fetchedAllTaxZones = false;

    /**
     * @var TaxAddressZone[]
     */
    private $_allTaxZones;


    /**
     * Get all tax zones.
     *
     * @return TaxAddressZone[]
     */
    public function getAllTaxZones(): array
    {
        if (!$this->_fetchedAllTaxZones) {
            $this->_allTaxZones = [];
            $rows = $this->_createTaxZonesQuery()->all();

            foreach ($rows as $row) {
                $this->_allTaxZones[$row['id']] = new TaxAddressZone($row);
            }

            $this->_fetchedAllTaxZones = true;
        }

        return $this->_allTaxZones;
    }

    /**
     * Get a tax zone by its ID.
     *
     * @param int $id
     * @return TaxAddressZone|null
     */
    public function getTaxZoneById($id)
    {
        if (isset($this->_allTaxZones[$id])) {
            return $this->_allTaxZones[$id];
        }

        if ($this->_fetchedAllTaxZones) {
            return null;
        }

        $result = $this->_createTaxZonesQuery()
            ->where(['id' => $id])
            ->one();

        if (!$result) {
            return null;
        }

        return $this->_allTaxZones[$id] = new TaxAddressZone($result);
    }

    /**
     * Save a tax zone.
     *
     * @param TaxAddressZone $model
     * @param bool $runValidation should we validate this zone before saving.
     * @return bool
     * @throws Exception
     * @throws \Exception
     */
    public function saveTaxZone(TaxAddressZone $model, bool $runValidation = true): bool
    {
        if ($model->id) {
            $record = TaxZoneRecord::findOne($model->id);

            if (!$record) {
                throw new Exception(Plugin::t('No tax zone exists with the ID “{id}”',
                    ['id' => $model->id]));
            }
        } else {
            $record = new TaxZoneRecord();
        }

        if ($runValidation && !$model->validate()) {
            Craft::info('Tax zone not saved due to validation error.', __METHOD__);

            return false;
        }

        $countryIds = $model->getCountryIds();
        $stateIds = $model->getStateIds();

        //setting attributes
        $record->name = $model->name;
        $record->description = $model->description;

        // If the condition formula changes, clear the cache for this zone.
        if (($record->zipCodeConditionFormula != $model->getZipCodeConditionFormula()) && $record->id) {
            TagDependency::invalidate(Craft::$app->cache, get_class($model) . ':' . $record->id);
        }

        $record->zipCodeConditionFormula = $model->getZipCodeConditionFormula();
        $record->isCountryBased = $model->isCountryBased;
        $record->default = $model->default;

        //validating given ids
        if ($record->isCountryBased) {
            $exist = CountryRecord::find()->where(['id' => $countryIds])->exists();

            if (!$exist) {
                $model->addError('countries', Plugin::t('At least one country must be selected.'));
            }
        } else {
            $exist = StateRecord::find()->where(['id' => $stateIds])->exists();

            if (!$exist) {
                $model->addError('states', Plugin::t('At least one state must be selected.'));
            }
        }

        if (!$model->validate()) {
            return false;
        }

        //saving
        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            // Save it!
            $record->save(false);

            // Now that we have a record ID, save it on the model
            $model->id = $record->id;

            // Clean out all old links
            TaxZoneCountryRecord::deleteAll(['taxZoneId' => $record->id]);
            TaxZoneStateRecord::deleteAll(['taxZoneId' => $record->id]);

            //saving new links
            if ($model->isCountryBased) {
                $rows = array_map(function($id) use ($model) {
                    return [$id, $model->id];
                }, $countryIds);
                $cols = ['countryId', 'taxZoneId'];
                $table = Table::TAXZONE_COUNTRIES;
            } else {
                $rows = array_map(function($id) use ($model) {
                    return [$id, $model->id];
                }, $stateIds);
                $cols = ['stateId', 'taxZoneId'];
                $table = Table::TAXZONE_STATES;
            }
            Craft::$app->getDb()->createCommand()->batchInsert($table, $cols, $rows)->execute();

            //If this was the default make all others not the default.
            if ($model->default) {
                TaxZoneRecord::updateAll(['default' => false], ['not', ['id' => $record->id]]);
            }

            $transaction->commit();
        } catch (\Exception $e) {
            $transaction->rollBack();

            throw $e;
        }

        return true;
    }

    /**
     * @param $id
     * @return bool
     */
    public function deleteTaxZoneById($id): bool
    {
        $record = TaxZoneRecord::findOne($id);

        if ($record) {
            return (bool)$record->delete();
        }

        return false;
    }


    /**
     * Returns a Query object prepped for retrieving tax zones.
     *
     * @return Query
     */
    private function _createTaxZonesQuery(): Query
    {
        return (new Query())
            ->select([
                'id',
                'name',
                'description',
                'isCountryBased',
                'zipCodeConditionFormula',
                'default',
            ])
            ->orderBy('name')
            ->from([Table::TAXZONES]);
    }
}
