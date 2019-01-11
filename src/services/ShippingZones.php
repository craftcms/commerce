<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\models\ShippingAddressZone;
use craft\commerce\records\Country as CountryRecord;
use craft\commerce\records\ShippingZone as ShippingZoneRecord;
use craft\commerce\records\ShippingZoneCountry as ShippingZoneCountryRecord;
use craft\commerce\records\ShippingZoneState as ShippingZoneStateRecord;
use craft\commerce\records\State as StateRecord;
use craft\db\Query;
use yii\base\Component;
use yii\base\Exception;

/**
 * Shipping zone service.
 *
 * @property ShippingAddressZone[]|array $allShippingZones
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class ShippingZones extends Component
{
    // Properties
    // =========================================================================

    /**
     * @var bool
     */
    private $_fetchedAllShippingZones = false;

    /**
     * @var ShippingAddressZone[]
     */
    private $_allShippingZones = [];

    // Public Methods
    // =========================================================================

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
     * Get a shipping zoneby its ID.
     *
     * @param int $id
     * @return ShippingAddressZone|null
     */
    public function getShippingZoneById($id)
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
     * @param ShippingAddressZone $model
     * @param bool $runValidation should we validate this rule before saving.
     * @return bool
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
        $record->isCountryBased = $model->isCountryBased;

        $countryIds = $model->getCountryIds();
        $stateIds = $model->getStateIds();

        //validating given ids
        if ($record->isCountryBased) {
            $exist = CountryRecord::find()->where(['id' => $countryIds])->exists();

            if (!$exist) {
                $model->addError('countries', Craft::t('commerce', 'At least one country must be selected.'));
            }
        } else {
            $exist = StateRecord::find()->where(['id' => $stateIds])->exists();

            if (!$exist) {
                $model->addError('states', Craft::t('commerce', 'At least one state must be selected.'));
            }
        }

        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            // Save it!
            $record->save(false);

            // Now that we have a record ID, save it on the model
            $model->id = $record->id;

            //deleting old links
            ShippingZoneCountryRecord::deleteAll(['shippingZoneId' => $record->id]);
            ShippingZoneStateRecord::deleteAll(['shippingZoneId' => $record->id]);

            //saving new links
            if ($model->isCountryBased) {
                $rows = array_map(function($id) use ($model) {
                    return [$id, $model->id];
                }, $countryIds);
                $cols = ['countryId', 'shippingZoneId'];
                $table = '{{%commerce_shippingzone_countries}}';
            } else {
                $rows = array_map(function($id) use ($model) {
                    return [$id, $model->id];
                }, $stateIds);
                $cols = ['stateId', 'shippingZoneId'];
                $table = '{{%commerce_shippingzone_states}}';
            }
            Craft::$app->getDb()->createCommand()->batchInsert($table, $cols, $rows)->execute();

            $transaction->commit();
        } catch (\Exception $e) {
            $transaction->rollBack();

            throw $e;
        }

        return true;
    }

    /**
     * @param int $id
     * @return bool
     */
    public function deleteShippingZoneById($id): bool
    {
        $record = ShippingZoneRecord::findOne($id);

        if ($record) {
            return (bool)$record->delete();
        }

        return false;
    }

    // Private methods
    // =========================================================================

    /**
     * Returns a Query object prepped for retrieving shipping zones.
     *
     * @return Query
     */
    private function _createShippingZonesQuery(): Query
    {
        return (new Query())
            ->select([
                'id',
                'name',
                'description',
                'isCountryBased',
            ])
            ->orderBy('name')
            ->from(['{{%commerce_shippingzones}}']);
    }
}
