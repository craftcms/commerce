<?php

namespace craft\commerce\records;

use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

/**
 * Shipping zone state record.
 *
 * @property int                          taxZoneId
 * @property \yii\db\ActiveQueryInterface $state
 * @property \yii\db\ActiveQueryInterface $shippingZone
 * @property int                          stateId
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class ShippingZoneState extends ActiveRecord
{
    // Public Methods
    // =========================================================================

    /**
     * @return string
     */
    public static function tableName(): string
    {
        return '{{%commerce_shippingzone_states}}';
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
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
