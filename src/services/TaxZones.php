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
use craft\commerce\records\Country as CountryRecord;
use craft\commerce\records\TaxZone as TaxZoneRecord;
use craft\commerce\records\TaxZoneCountry as TaxZoneCountryRecord;
use craft\commerce\records\TaxZoneState as TaxZoneStateRecord;
use craft\commerce\records\State as StateRecord;
use craft\db\Query;
use craft\helpers\Json;
use Throwable;
use yii\base\Component;
use yii\base\Exception;
use yii\caching\TagDependency;
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
     * @var bool
     */
    private bool $_fetchedAllTaxZones = false;

    /**
     * @var TaxAddressZone[]
     */
    private array $_allTaxZones = [];

    /**
     * Get all tax zones.
     *
     * @return TaxAddressZone[]
     */
    public function getAllTaxZones(): array
    {
        if (!$this->_fetchedAllTaxZones) {
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
     */
    public function getTaxZoneById(int $id): ?TaxAddressZone
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
     * @param bool $runValidation should we validate this rule before saving.
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
            Craft::info('Tax rule not saved due to validation error.', __METHOD__);

            return false;
        }

        //setting attributes
        $record->name = $model->name;
        $record->description = $model->description;

        // If the condition formula changes, clear the cache for this zone.
        if (($record->zipCodeConditionFormula != $model->getZipCodeConditionFormula()) && $record->id) {
            TagDependency::invalidate(Craft::$app->cache, get_class($model) . ':' . $record->id);
        }

        $record->zipCodeConditionFormula = $model->getZipCodeConditionFormula();
        $record->isCountryBased = $model->isCountryBased;
        $record->countryCode = $model->countryCode;
        $record->countries = Json::encode($model->getCountries());
        $record->administrativeAreas = Json::encode($model->getAdministrativeAreas());

        $record->save();
        $model->id = $record->id;

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
    private function _createTaxZonesQuery(): Query
    {
        return (new Query())
            ->select([
                'administrativeAreas',
                'countries',
                'countryCode',
                'dateCreated',
                'dateUpdated',
                'description',
                'id',
                'isCountryBased',
                'name',
                'zipCodeConditionFormula',
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
        $this->_fetchedAllTaxZones = false;
        $this->_allTaxZones = [];
    }
}
