<?php

namespace craft\commerce\records;

use craft\db\ActiveRecord;

/**
 * Product record.
 *
 * @property int         $id
 * @property int         $taxCategoryId
 * @property int         $shippingCategoryId
 * @property int         $typeId
 * @property \DateTime   $postDate
 * @property \DateTime   $expiryDate
 * @property bool        $promotable
 * @property bool        $freeShipping
 *
 * @property int         defaultVariantId
 * @property string      defaultSku
 * @property float       defaultPrice
 * @property float       defaultHeight
 * @property float       defaultLength
 * @property float       defaultWidth
 * @property float       defaultWeight
 *
 * @property Variant     $implicit
 * @property Variant[]   $variants
 * @property TaxCategory $taxCategory
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class Product extends ActiveRecord
{

    /**
     * @return string
     */
    public static function tableName(): string
    {
        return '{{%commerce_products}}';
    }

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
//            ],
//            'type' => [
//                static::BELONGS_TO,
//                'ProductType',
//                'onDelete' => static::CASCADE
//            ],
//            'variants' => [
//                static::HAS_MANY,
//                'Variant',
//                'productId'
//            ],
//            'taxCategory' => [
//                static::BELONGS_TO,
//                'TaxCategory',
//                'required' => true
//            ],
//            'shippingCategory' => [
//                static::BELONGS_TO,
//                'ShippingCategory',
//                'required' => true
//            ],
//        ];
//    }
//
//    /**
//     * @return array
//     */
//    public function defineIndexes()
//    {
//        return [
//            ['columns' => ['typeId']],
//            ['columns' => ['postDate']],
//            ['columns' => ['expiryDate']],
//        ];
//    }
//
//    /**
//     * @return array
//     */
//    protected function defineAttributes()
//    {
//        return [
//            'postDate' => AttributeType::DateTime,
//            'expiryDate' => AttributeType::DateTime,
//            'promotable' => AttributeType::Bool,
//            'freeShipping' => AttributeType::Bool,
//
//            'defaultVariantId' => [AttributeType::Number, 'unsigned' => true],
//            'defaultSku' => [AttributeType::String, 'label' => 'SKU'],
//            'defaultPrice' => [AttributeType::Number, 'decimals' => 4],
//            'defaultHeight' => [AttributeType::Number, 'decimals' => 4],
//            'defaultLength' => [AttributeType::Number, 'decimals' => 4],
//            'defaultWidth' => [AttributeType::Number, 'decimals' => 4],
//            'defaultWeight' => [AttributeType::Number, 'decimals' => 4]
//        ];
//    }

}
