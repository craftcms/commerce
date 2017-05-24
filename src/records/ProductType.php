<?php

namespace craft\commerce\records;

use craft\db\ActiveRecord;
use craft\records\FieldLayout;

/**
 * Product type record.
 *
 * @property int         $id
 * @property string      $name
 * @property string      $handle
 * @property bool        $hasUrls
 * @property bool        $hasDimensions
 * @property bool        $hasVariants
 * @property bool        $hasVariantTitleField
 * @property string      $template
 * @property string      $titleFormat
 * @property string      $skuFormat
 * @property string      $descriptionFormat
 * @property int         $fieldLayoutId
 * @property int         $variantFieldLayoutId
 *
 * @property FieldLayout $fieldLayout
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class ProductType extends ActiveRecord
{
    /**
     * @return string
     */
    public static function tableName()
    {
        return 'commerce_producttypes';
    }

//    /**
//     * @return array
//     */
//    public function defineIndexes()
//    {
//        return [
//            ['columns' => ['handle'], 'unique' => true],
//        ];
//    }
//
//    /**
//     * @return array
//     */
//    public function defineRelations()
//    {
//        return [
//            'shippingCategories' => [
//                static::MANY_MANY,
//                'ShippingCategory',
//                'commerce_producttypes_shippingcategories(shippingCategoryId, productTypeId)'
//            ],
//            'taxCategories' => [
//                static::MANY_MANY,
//                'TaxCategory',
//                'commerce_producttypes_taxcategories(taxCategoryId, productTypeId)'
//            ],
//            'fieldLayout' => [
//                static::BELONGS_TO,
//                'FieldLayout',
//                'onDelete' => static::SET_NULL
//            ],
//            'variantFieldLayout' => [
//                static::BELONGS_TO,
//                'FieldLayout',
//                'onDelete' => static::SET_NULL
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
//            'name' => [AttributeType::Name, 'required' => true],
//            'handle' => [AttributeType::Handle, 'required' => true],
//            'hasUrls' => AttributeType::Bool,
//            'hasDimensions' => AttributeType::Bool,
//            'hasVariants' => AttributeType::Bool,
//            'hasVariantTitleField' => [AttributeType::Bool, 'default' => 1],
//            'titleFormat' => [AttributeType::String, 'required' => true],
//            'skuFormat' => AttributeType::String,
//            'descriptionFormat' => AttributeType::String,
//            'template' => AttributeType::Template
//        ];
//    }

}
