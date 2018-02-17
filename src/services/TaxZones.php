<?php

namespace craft\commerce\services;

use Craft;
use craft\commerce\models\TaxZone;
use craft\commerce\records\Country as CountryRecord;
use craft\commerce\records\State as StateRecord;
use craft\commerce\records\TaxZone as TaxZoneRecord;
use craft\commerce\records\TaxZoneCountry as TaxZoneCountryRecord;
use craft\commerce\records\TaxZoneState as TaxZoneStateRecord;
use craft\db\Query;
use yii\base\Component;
use yii\base\Exception;

/**
 * Tax zone service.
 *
 * @property TaxZone[]|array $allTaxZones
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class TaxZones extends Component
{
    // Properties
    // =========================================================================

    /**
     * @var bool
     */
    private $_fetchedAllTaxZones = false;

    /**
     * @var TaxZone[]
     */
    private $_allTaxZones;

    // Public Methods
    // =========================================================================

    /**
     * @return TaxZone[]
     */
    public function getAllTaxZones(): array
    {
        if (!$this->_fetchedAllTaxZones) {
            $this->_allTaxZones = [];
            $rows = $this->_createTaxZonesQuery()->all();

            foreach ($rows as $row) {
                $this->_allTaxZones[$row['id']] = new TaxZone($row);
            }

            $this->_fetchedAllTaxZones = true;
        }

        return $this->_allTaxZones;
    }

    /**
     * @param int $id
     * @return TaxZone|null
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

        return $this->_allTaxZones[$id] = new TaxZone($result);
    }

    /**
     * @param TaxZone $model
     * @param array $countryIds
     * @param array $stateIds
     * @return bool
     * @throws Exception
     * @throws \Exception
     */
    public function saveTaxZone(TaxZone $model, $countryIds, $stateIds): bool
    {
        if ($model->id) {
            $record = TaxZoneRecord::findOne($model->id);

            if (!$record) {
                throw new Exception(Craft::t('commerce', 'No tax zone exists with the ID “{id}”',
                    ['id' => $model->id]));
            }
        } else {
            $record = new TaxZoneRecord();
        }

        //setting attributes
        $record->name = $model->name;
        $record->description = $model->description;
        $record->countryBased = $model->countryBased;
        $record->default = $model->default;

        $record->validate();
        $model->addErrors($record->getErrors());

        //validating given ids
        if ($record->countryBased) {
            $exist = CountryRecord::find()->where(['id' => $countryIds])->exists();

            if (!$exist) {
                $model->addError('countries', 'Please select some countries');
            }
        } else {
            $exist = StateRecord::find()->where(['id' => $stateIds])->exists();

            if (!$exist) {
                $model->addError('states', 'Please select some states');
            }
        }

        //saving
        if (!$model->hasErrors()) {
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
                if ($model->countryBased) {
                    $rows = array_map(function($id) use ($model) {
                        return [$id, $model->id];
                    }, $countryIds);
                    $cols = ['countryId', 'taxZoneId'];
                    $table = TaxZoneCountryRecord::tableName();
                } else {
                    $rows = array_map(function($id) use ($model) {
                        return [$id, $model->id];
                    }, $stateIds);
                    $cols = ['stateId', 'taxZoneId'];
                    $table = TaxZoneStateRecord::tableName();
                }
                Craft::$app->getDb()->createCommand()->batchInsert($table, $cols, $rows)->execute();

                //If this was the default make all others not the default.
                if ($model->default) {
                    TaxZoneRecord::updateAll(['default' => 0], 'id <> :thisId', [':thisId' => $record->id]);
                }

                $transaction->commit();
            } catch (\Exception $e) {
                $transaction->rollBack();

                throw $e;
            }

            return true;
        }

        return false;
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

    // Private methods
    // =========================================================================

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
                'countryBased',
                'default',
            ])
            ->orderBy('name')
            ->from(['{{%commerce_taxzones}}']);
    }
}
