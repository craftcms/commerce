<?php

namespace craft\commerce\records;

use craft\db\ActiveRecord;
use craft\records\Element;
use yii\db\ActiveQueryInterface;

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

    /**
     * Returns the product’s element.
     *
     * @return ActiveQueryInterface The relational query object.
     */
    public function getElement(): ActiveQueryInterface
    {
        return $this->hasOne(Element::class, ['id' => 'id']);
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
}
