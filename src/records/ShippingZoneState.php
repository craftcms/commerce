<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\records;

use craft\commerce\db\Table;
use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

/**
 * Shipping zone state record.
 *
 * @property int $id
 * @property ActiveQueryInterface $shippingZone
 * @property int $shippingZoneId
 * @property ActiveQueryInterface $state
 * @property int $stateId
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class ShippingZoneState extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::SHIPPINGZONE_STATES;
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['shippingZoneId', 'stateId'], 'unique', 'targetAttribute' => ['shippingZoneId', 'stateId']]
        ];
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getShippingZone(): ActiveQueryInterface
    {
        return $this->hasOne(ShippingZone::class, ['id' => 'shippingZoneId']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getState(): ActiveQueryInterface
    {
        return $this->hasOne(State::class, ['id' => 'stateId']);
    }
}
