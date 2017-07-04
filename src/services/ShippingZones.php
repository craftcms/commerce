<?php

namespace craft\commerce\services;


use Craft;
use craft\commerce\models\ShippingZone;
use craft\commerce\records\Country as CountryRecord;
use craft\commerce\records\ShippingZone as ShippingZoneRecord;
use craft\commerce\records\ShippingZoneCountry as ShippingZoneCountryRecord;
use craft\commerce\records\ShippingZoneState as ShippingZoneStateRecord;
use craft\commerce\records\State as StateRecord;
use craft\db\Query;
use yii\base\Component;

/**
 * Shipping zone service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class ShippingZones extends Component
{
    /**
     * @var bool
     */
    private $_fetchedAllShippingZones = false;

    /**
     * @var ShippingZone[]
     */
    private $_allShippingZones = [];

    /**
     * @return ShippingZone[]
     */
    public function getAllShippingZones(): array
    {
        if (!$this->_fetchedAllShippingZones) {
            $rows = $this->_createShippingZonesQuery()->all();

            foreach ($rows as $row) {
                $this->_allShippingZones[$row['id']] = new ShippingZone($row);
            }

            $this->_fetchedAllShippingZones = true;
        }

        return $this->_allShippingZones;
    }

    /**
     * @param int $id
     *
     * @return ShippingZone|null
     */
    public function getShippingZoneById($id)
    {
        if (isset($this->_allShippingZones[$id])) {
            return $this->_allShippingZones[$id];
        }

        if ($this->_fetchedAllShippingZones) {
            return null;
        }

        $row = $this->_createShippingZonesQuery()
            ->where(['id' => $id])
            ->one();

        if (!$row) {
            return null;
        }

        return $this->_allShippingZones[$id] = new ShippingZone($row);
    }
    
    /**
     * @param ShippingZone $model
     * @param array        $countryIds
     * @param array        $stateIds
     *
     * @return bool
     * @throws \Exception
     */
    public function saveShippingZone(ShippingZone $model, $countryIds, $stateIds)
    {
        if ($model->id) {
            $record = ShippingZoneRecord::findOne($model->id);

            if (!$record) {
                throw new Exception(Craft::t('commerce', 'No shipping zone exists with the ID “{id}”', ['id' => $model->id]));
            }
        } else {
            $record = new ShippingZoneRecord();
        }

        //setting attributes
        $record->name = $model->name;
        $record->description = $model->description;
        $record->countryBased = $model->countryBased;

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

                //deleting old links
                ShippingZoneCountryRecord::deleteAll(['shippingZoneId' => $record->id]);
                ShippingZoneStateRecord::deleteAll(['shippingZoneId' => $record->id]);

                //saving new links
                if ($model->countryBased) {
                    $rows = array_map(function($id) use ($model) {
                        return [$id, $model->id];
                    }, $countryIds);
                    $cols = ['countryId', 'shippingZoneId'];
                    $table = ShippingZoneCountryRecord::tableName();
                } else {
                    $rows = array_map(function($id) use ($model) {
                        return [$id, $model->id];
                    }, $stateIds);
                    $cols = ['stateId', 'shippingZoneId'];
                    $table = ShippingZoneStateRecord::tableName();
                }
                Craft::$app->getDb()->createCommand()->batchInsert($table, $cols, $rows)->execute();

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
     * @param int $id
     *
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
                'countryBased',
            ])
            ->orderBy('name')
            ->from(['{{%commerce_shippingzones}}']);
    }
}
