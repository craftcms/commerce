<?php

namespace craft\commerce\records;

use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

/**
 * Taz zone country
 *
 * @property int                          $shippingZoneId
 * @property \yii\db\ActiveQueryInterface $shippingZone
 * @property \yii\db\ActiveQueryInterface $country
 * @property int                          $countryId
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class ShippingZoneCountry extends ActiveRecord
{
    /**
     * @return string
     */
    public static function tableName(): string
    {
        return '{{%commerce_shippingzone_countries}}';
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['shippingZoneId', 'countryId'], 'unique', 'targetAttribute' => ['shippingZoneId', 'countryId']]
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
    public function getCountry(): ActiveQueryInterface
    {
        return $this->hasOne(Country::class, ['id' => 'countryId']);
    }
}