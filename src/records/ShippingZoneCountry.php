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
 * Taz zone country
 *
 * @property ActiveQueryInterface $country
 * @property int $countryId
 * @property int $id
 * @property ActiveQueryInterface $shippingZone
 * @property int $shippingZoneId
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class ShippingZoneCountry extends ActiveRecord
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::SHIPPINGZONE_COUNTRIES;
    }

    /**
     * @inheritdoc
     */
    public function rules()
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
