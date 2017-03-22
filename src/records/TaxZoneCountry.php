<?php
namespace craft\commerce\records;

use craft\db\ActiveRecord;

/**
 * Taz zone country
 *
 * @property int $taxZoneId
 * @property int $countryId
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class TaxZoneCountry extends ActiveRecord
{

    /**
     * @return string
     */
    public static function tableName()
    {
        return "commerce_taxzone_countries";
    }

//    /**
//     * @return array
//     */
//    public function defineIndexes()
//    {
//        return [
//            ['columns' => ['taxZoneId']],
//            ['columns' => ['countryId']],
//            ['columns' => ['taxZoneId', 'countryId'], 'unique' => true],
//        ];
//    }
//
//
//    /**
//     * @inheritDoc \craft\db\ActiveRecord::defineRelations()
//     *
//     * @return array
//     */
//    public function defineRelations()
//    {
//        return [
//            'taxZone' => [
//                static::BELONGS_TO,
//                'TaxZone',
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