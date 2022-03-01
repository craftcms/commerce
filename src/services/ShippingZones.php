<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\db\Table;
use craft\commerce\models\ShippingAddressZone;
use craft\commerce\records\Country as CountryRecord;
use craft\commerce\records\ShippingZone as ShippingZoneRecord;
use craft\commerce\records\ShippingZoneCountry as ShippingZoneCountryRecord;
use craft\commerce\records\ShippingZoneState as ShippingZoneStateRecord;
use craft\commerce\records\State as StateRecord;
use craft\db\Query;
use craft\helpers\Json;
use Throwable;
use yii\base\Component;
use yii\base\Exception;
use yii\caching\TagDependency;
use yii\db\StaleObjectException;

/**
 * Shipping zone service.
 *
 * @property ShippingAddressZone[]|array $allShippingZones
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class ShippingZones extends Component
{
    /**
     * @var bool
     */
    private bool $_fetchedAllShippingZones = false;

    /**
     * @var ShippingAddressZone[]
     */
    private array $_allShippingZones = [];

    /**
     * Get all shipping zones.
     *
     * @return ShippingAddressZone[]
     */
    public function getAllShippingZones(): array
    {
        if (!$this->_fetchedAllShippingZones) {
            $rows = $this->_createShippingZonesQuery()->all();

            foreach ($rows as $row) {
                $this->_allShippingZones[$row['id']] = new ShippingAddressZone($row);
            }

            $this->_fetchedAllShippingZones = true;
        }

        return $this->_allShippingZones;
    }

    /**
     * Get a shipping zone by its ID.
     */
    public function getShippingZoneById(int $id): ?ShippingAddressZone
    {
        if (isset($this->_allShippingZones[$id])) {
            return $this->_allShippingZones[$id];
        }

        if ($this->_fetchedAllShippingZones) {
            return null;
        }

        $result = $this->_createShippingZonesQuery()
            ->where(['id' => $id])
            ->one();

        if (!$result) {
            return null;
        }

        return $this->_allShippingZones[$id] = new ShippingAddressZone($result);
    }

    /**
     * Save a shipping zone.
     *
     * @param bool $runValidation should we validate this rule before saving.
     * @throws \Exception
     * @throws Exception
     */
    public function saveShippingZone(ShippingAddressZone $model, bool $runValidation = true): bool
    {
        if ($model->id) {
            $record = ShippingZoneRecord::findOne($model->id);

            if (!$record) {
                throw new Exception(Craft::t('commerce', 'No shipping zone exists with the ID “{id}”', ['id' => $model->id]));
            }
        } else {
            $record = new ShippingZoneRecord();
        }

        if ($runValidation && !$model->validate()) {
            Craft::info('Shipping rule not saved due to validation error.', __METHOD__);

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
    public function deleteShippingZoneById(int $id): bool
    {
        $record = ShippingZoneRecord::findOne($id);

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
     * Returns a Query object prepped for retrieving shipping zones.
     */
    private function _createShippingZonesQuery(): Query
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
            ->from([Table::SHIPPINGZONES]);
    }

    /**
     * Clear memoization.
     *
     * @since 3.2.5
     */
    private function _clearCaches(): void
    {
        $this->_fetchedAllShippingZones = false;
        $this->_allShippingZones = [];
    }
}
