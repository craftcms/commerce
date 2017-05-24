<?php

namespace craft\commerce\records;

use craft\db\ActiveRecord;

/**
 * Tax zone record.
 *
 * @property int       $id
 * @property string    $name
 * @property string    $description
 * @property bool      $countryBased
 * @property bool      $default
 *
 * @property Country[] $countries
 * @property State[]   $states
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class TaxZone extends ActiveRecord
{

    /**
     * @return string
     */
    public static function tableName()
    {
        return 'commerce_taxzones';
    }

//    /**
//     * @return array
//     */
//    public function defineIndexes()
//    {
//        return [
//            ['columns' => ['name'], 'unique' => true],
//        ];
//    }
//
//    /**
//     * @return array
//     */
//    public function defineRelations()
//    {
//        return [
//            'countries' => [
//                static::MANY_MANY,
//                'Country',
//                'commerce_taxzone_countries(countryId, taxZoneId)'
//            ],
//            'states' => [
//                static::MANY_MANY,
//                'State',
//                'commerce_taxzone_states(stateId, taxZoneId)'
//            ],
//        ];
//    }
//
//    /**
//     * @return array
//     */
//    protected function defineAttributes()
//    {
//        return [
//            'name' => [AttributeType::String, 'required' => true],
//            'description' => AttributeType::String,
//            'countryBased' => [
//                AttributeType::Bool,
//                'required' => true,
//                'default' => 1
//            ],
//            'default' => [
//                AttributeType::Bool,
//                'default' => 0,
//                'required' => true
//            ],
//        ];
//    }
}