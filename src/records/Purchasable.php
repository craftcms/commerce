<?php

namespace craft\commerce\records;

use craft\db\ActiveRecord;

/**
 * Purchasable record.
 *
 * @property int     $id
 * @property string  $sku
 * @property float   $price
 *
 * @property Variant $implicit
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class Purchasable extends ActiveRecord
{

    /**
     * @return string
     */
    public static function tableName()
    {
        return 'commerce_purchasables';
    }

//    /**
//     * @return array
//     */
//    public function defineIndexes()
//    {
//        return [
//            ['columns' => ['sku'], 'unique' => true],
//        ];
//    }
//
//    /**
//     * @return array
//     */
//    public function defineRelations()
//    {
//        return [
//            'element' => [
//                static::BELONGS_TO,
//                'Element',
//                'id',
//                'required' => true,
//                'onDelete' => static::CASCADE
//            ]
//        ];
//    }
//
//    /**
//     * @return array
//     */
//    protected function defineAttributes()
//    {
//        return [
//            'sku' => [AttributeType::String, 'required' => true],
//            'price' => [
//                AttributeType::Number,
//                'decimals' => 4,
//                'required' => true
//            ]
//        ];
//    }

}