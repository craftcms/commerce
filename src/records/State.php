<?php

namespace craft\commerce\records;

use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

/**
 * State record.
 *
 * @property int     $id
 * @property string  $name
 * @property string  $abbreviation
 * @property int     $countryId
 *
 * @property Country $country
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class State extends ActiveRecord
{

    /**
     * @return string
     */
    public static function tableName()
    {
        return 'commerce_states';
    }

    /**
     * @return array
     */
//    public function defineIndexes()
//    {
//        return [
//            ['columns' => ['name', 'countryId'], 'unique' => true],
//        ];
//    }

    /**
     * Returns the address's state
     *
     * @return ActiveQueryInterface The relational query object.
     */
    public function getCountry(): ActiveQueryInterface
    {
        return $this->hasOne(Country::class, ['id' => 'countryId']);
    }

//    /**
//     * @return array
//     */
//    public function defineRelations()
//    {
//        return [
//            'country' => [
//                static::BELONGS_TO,
//                'Country',
//                'onDelete' => self::CASCADE,
//                'onUpdate' => self::CASCADE,
//                'required' => true
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
//            'abbreviation' => AttributeType::String,
//            'countryId' => [AttributeType::Number, 'required' => true],
//        ];
//    }
}