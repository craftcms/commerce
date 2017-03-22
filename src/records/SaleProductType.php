<?php

namespace craft\commerce\records;

use craft\db\ActiveRecord;

/**
 * Sale product type record.
 *
 * @property int $id
 * @property int $saleId
 * @property int $productTypeId
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class SaleProductType extends ActiveRecord
{
    /**
     * @return string
     */
    public static function tableName()
    {
        return 'commerce_sale_producttypes';
    }

//    /**
//     * @return array
//     */
//    public function defineIndexes()
//    {
//        return [
//            ['columns' => ['saleId', 'productTypeId'], 'unique' => true],
//        ];
//    }
//
//    /**
//     * @return array
//     */
//    public function defineRelations()
//    {
//        return [
//            'sale' => [
//                static::BELONGS_TO,
//                'Sale',
//                'onDelete' => self::CASCADE,
//                'onUpdate' => self::CASCADE,
//                'required' => true
//            ],
//            'productType' => [
//                static::BELONGS_TO,
//                'ProductType',
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
//            'saleId' => [AttributeType::Number, 'required' => true],
//            'productTypeId' => [AttributeType::Number, 'required' => true],
//        ];
//    }

}