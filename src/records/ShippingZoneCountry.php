<?php
namespace craft\commerce\records;

use craft\db\ActiveRecord;

/**
 * Taz zone country
 *
 * @property int $shippingZoneId
 * @property int $countryId
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
    public static function tableName()
    {
        return "commerce_shippingzone_countries";
    }

//    /**
//     * @return array
//     */
//    public function defineIndexes()
//    {
//        return [
//            ['columns' => ['shippingZoneId']],
//            ['columns' => ['countryId']],
//            ['columns' => ['shippingZoneId', 'countryId'], 'unique' => true],
//        ];
//    }


//    /**
//     * @inheritDoc BaseRecord::defineRelations()
//     *
//     * @return array
//     */
//    public function defineRelations()
//    {
//        return [
//            'shippingZone' => [
//                static::BELONGS_TO,
//                'ShippingZone',
//                'onDelete' => self::CASCADE,
//                'onUpdate' => self::CASCADE,
//                'required' => true
//            ],
//            'country' => [
//                static::BELONGS_TO,
//                'Country',
//                'onDelete' => self::CASCADE,
//                'onUpdate' => self::CASCADE,
//                'required' => true
//            ],
//        ];
//    }

}