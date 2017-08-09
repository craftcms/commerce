<?php

namespace craft\commerce\records;

use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

/**
 * Shipping rule record.
 *
 * @property int                          $id
 * @property string                       $name
 * @property string                       $description
 * @property int                          $shippingZoneId
 * @property int                          $methodId
 * @property int                          $priority
 * @property bool                         $enabled
 * @property int                          $minQty
 * @property int                          $maxQty
 * @property float                        $minTotal
 * @property float                        $maxTotal
 * @property float                        $minWeight
 * @property float                        $maxWeight
 * @property float                        $baseRate
 * @property float                        $perItemRate
 * @property float                        $weightRate
 * @property float                        $percentageRate
 * @property float                        $minRate
 * @property float                        $maxRate
 *
 * @property Country                      $country
 * @property State                        $state
 * @property \yii\db\ActiveQueryInterface $shippingZone
 * @property ShippingMethod               $method
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class ShippingRule extends ActiveRecord
{
    /**
     * @return string
     */
    public static function tableName(): string
    {
        return '{{%commerce_shippingrules}}';
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            ['name', 'targetAttribute' => ['name', 'methodId']]
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
    public function getMethod(): ActiveQueryInterface
    {
        return $this->hasOne(ShippingZone::class, ['id' => 'shippingMethodId']);
    }
}