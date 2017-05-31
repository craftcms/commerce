<?php

namespace craft\commerce\records;

use craft\db\ActiveRecord;
use craft\records\UserGroup;

/**
 * Discount record.
 *
 * @property int           $id
 * @property string        $name
 * @property string        $description
 * @property string        $code
 * @property int           $perUserLimit
 * @property int           $perEmailLimit
 * @property int           $totalUseLimit
 * @property int           $totalUses
 * @property \DateTime     $dateFrom
 * @property \DateTime     $dateTo
 * @property int           $purchaseTotal
 * @property int           $purchaseQty
 * @property int           $maxPurchaseQty
 * @property float         $baseDiscount
 * @property float         $perItemDiscount
 * @property float         $percentDiscount
 * @property bool          $excludeOnSale
 * @property bool          $freeShipping
 * @property bool          $allGroups
 * @property bool          $allProducts
 * @property bool          $allProductTypes
 * @property bool          $enabled
 * @property bool          $stopProcessing
 * @property bool          $sortOrder
 *
 * @property Product[]     $products
 * @property ProductType[] $productTypes
 * @property UserGroup[]   $groups
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class Discount extends ActiveRecord
{
    /**
     * @return string
     */
    public static function tableName()
    {
        return '{{%commerce_discounts}}';
    }



//    /**
//     * @return array
//     */
//    public function defineRelations()
//    {
//        return [
//            'groups' => [
//                static::MANY_MANY,
//                'UserGroup',
//                'commerce_discount_usergroups(discountId, userGroupId)'
//            ],
//            'products' => [
//                static::MANY_MANY,
//                'Product',
//                'commerce_discount_products(discountId, productId)'
//            ],
//            'productTypes' => [
//                static::MANY_MANY,
//                'ProductType',
//                'commerce_discount_producttypes(discountId, productTypeId)'
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
//            ['columns' => ['code'], 'unique' => true],
//            ['columns' => ['dateFrom']],
//            ['columns' => ['dateTo']],
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
//            'description' => AttributeType::Mixed,
//            'code' => AttributeType::String,
//            'perUserLimit' => [
//                AttributeType::Number,
//                'required' => true,
//                'min' => 0,
//                'default' => 0
//            ],
//            'perEmailLimit' => [
//                AttributeType::Number,
//                'required' => true,
//                'min' => 0,
//                'default' => 0
//            ],
//            'totalUseLimit' => [
//                AttributeType::Number,
//                'required' => true,
//                'min' => 0,
//                'default' => 0
//            ],
//            'totalUses' => [
//                AttributeType::Number,
//                'required' => true,
//                'min' => 0,
//                'default' => 0
//            ],
//            'dateFrom' => AttributeType::DateTime,
//            'dateTo' => AttributeType::DateTime,
//            'purchaseTotal' => [
//                AttributeType::Number,
//                'required' => true,
//                'default' => 0
//            ],
//            'purchaseQty' => [
//                AttributeType::Number,
//                'required' => true,
//                'default' => 0
//            ],
//            'maxPurchaseQty' => [
//                AttributeType::Number,
//                'required' => true,
//                'default' => 0
//            ],
//            'baseDiscount' => [
//                AttributeType::Number,
//                'decimals' => 4,
//                'required' => true,
//                'default' => 0
//            ],
//            'perItemDiscount' => [
//                AttributeType::Number,
//                'decimals' => 4,
//                'required' => true,
//                'default' => 0
//            ],
//            'percentDiscount' => [
//                AttributeType::Number,
//                'decimals' => 4,
//                'required' => true,
//                'default' => 0
//            ],
//            'excludeOnSale' => [
//                AttributeType::Bool,
//                'required' => true,
//                'default' => 0
//            ],
//            'freeShipping' => [
//                AttributeType::Bool,
//                'required' => true,
//                'default' => 0
//            ],
//            'allGroups' => [
//                AttributeType::Bool,
//                'required' => true,
//                'default' => 0
//            ],
//            'allProducts' => [
//                AttributeType::Bool,
//                'required' => true,
//                'default' => 0
//            ],
//            'allProductTypes' => [
//                AttributeType::Bool,
//                'required' => true,
//                'default' => 0
//            ],
//            'enabled' => [
//                AttributeType::Bool,
//                'required' => true,
//                'default' => 1
//            ],
//            'stopProcessing' => [
//                AttributeType::Bool,
//                'required' => true,
//                'default' => false
//            ],
//            'sortOrder' => AttributeType::Number
//        ];
//    }

}
