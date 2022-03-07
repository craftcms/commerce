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
use craft\commerce\records\ShippingZone as ShippingZoneRecord;
use craft\db\Query;
use Throwable;
use yii\base\Component;
use yii\base\Exception;
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
     * @var ShippingAddressZone[]
     */
    private array $_allZones = [];

    /**
     * Get all shipping zones.
     *
     * @return ShippingAddressZone[]
     */
    public function getAllShippingZones(): array
    {
        $rows = $this->_createQuery()->all();

        foreach ($rows as $row) {
            $this->_allZones[$row['id']] = new ShippingAddressZone($row);
        }

        return $this->_allZones;
    }

    /**
     * Get a shipping zone by its ID.
     */
    public function getShippingZoneById(int $id): ?ShippingAddressZone
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

        return $this->_allZones[$id] = new ShippingAddressZone($result);
    }

    /**
     * Save a shipping zone.
     *
     * @param bool $runValidation should we validate this zone before saving
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
            Craft::info('Shipping zone not saved due to validation error.', __METHOD__);

            return false;
        }

        //setting attributes
        $record->name = $model->name;
        $record->description = $model->description;
        $record->condition = $model->getCondition()->getConfig();
        $this->_clearCaches();

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
    private function _createQuery(): Query
    {
        return (new Query())
            ->select([
                'condition',
                'dateCreated',
                'dateUpdated',
                'description',
                'id',
                'name',
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
        $this->_allZones = [];
    }
}
