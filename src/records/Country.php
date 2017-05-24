<?php

namespace craft\commerce\records;

use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

/**
 * Country record.
 *
 * @property int    $id
 * @property string $name
 * @property string $iso
 * @property bool   $stateRequired
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class Country extends ActiveRecord
{

    /**
     * @return string
     */
    public static function tableName()
    {
        return 'commerce_countries';
    }

    /**
     * Returns the country's states
     *
     * @return ActiveQueryInterface The relational query object.
     */
    public function getStates()
    {
        return $this->hasMany(State::class, ['id', 'countryId']);
    }
    /**
     * @return array
     */
//    public function defineIndexes()
//    {
//        return [
//            ['columns' => ['name'], 'unique' => true],
//            ['columns' => ['iso'], 'unique' => true],
//        ];
//    }

    /**
     * @return array
     */
//    protected function defineAttributes()
//    {
//        return [
//            'name' => [AttributeType::String, 'required' => true],
//            'iso' => [
//                AttributeType::String,
//                'required' => true,
//                'maxLength' => 2
//            ],
//            'stateRequired' => [
//                AttributeType::Bool,
//                'required' => true,
//                'default' => 0
//            ],
//        ];
//    }
}