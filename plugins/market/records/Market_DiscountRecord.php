<?php

namespace Craft;

/**
 * Class Market_DiscountRecord
 *
 * @property int                        id
 * @property string                     name
 * @property string                     description
 * @property string                     code
 * @property int                        perUserLimit
 * @property int                        totalUseLimit
 * @property int                        totalUses
 * @property DateTime                   dateFrom
 * @property DateTime                   dateTo
 * @property int                        purchaseTotal
 * @property int                        purchaseQty
 * @property float                      baseDiscount
 * @property float                      perItemDiscount
 * @property float                      percentDiscount
 * @property bool                       excludeOnSale
 * @property bool                       freeShipping
 * @property bool                       allGroups
 * @property bool                       allProducts
 * @property bool                       allProductTypes
 * @property bool                       enabled
 *
 * @property Market_ProductRecord[]     products
 * @property Market_ProductTypeRecord[] productTypes
 * @property UserGroupRecord[]          groups
 * @package Craft
 */
class Market_DiscountRecord extends BaseRecord
{
    public function getTableName()
    {
        return 'market_discounts';
    }

    public function defineRelations()
    {
        return [
            'groups'       => [
                static::MANY_MANY,
                'UserGroupRecord',
                'market_discount_usergroups(discountId, userGroupId)'
            ],
            'products'     => [
                static::MANY_MANY,
                'Market_ProductRecord',
                'market_discount_products(discountId, productId)'
            ],
            'productTypes' => [
                static::MANY_MANY,
                'Market_ProductTypeRecord',
                'market_discount_producttypes(discountId, productTypeId)'
            ],
        ];
    }

    public function defineIndexes()
    {
        return [
            ['columns' => ['code'], 'unique' => true],
            ['columns' => ['dateFrom']],
            ['columns' => ['dateTo']],
        ];
    }

    protected function defineAttributes()
    {
        return [
            'name'            => [AttributeType::Name, 'required' => true],
            'description'     => AttributeType::Mixed,
            'code'            => [AttributeType::String, 'required' => true],
            'perUserLimit'    => [
                AttributeType::Number,
                'required' => true,
                'min'      => 0,
                'default'  => 0
            ],
            'totalUseLimit'   => [
                AttributeType::Number,
                'required' => true,
                'min'      => 0,
                'default'  => 0
            ],
            'totalUses'       => [
                AttributeType::Number,
                'required' => true,
                'min'      => 0,
                'default'  => 0
            ],
            'dateFrom'        => AttributeType::DateTime,
            'dateTo'          => AttributeType::DateTime,
            'purchaseTotal'   => [
                AttributeType::Number,
                'required' => true,
                'default'  => 0
            ],
            'purchaseQty'     => [
                AttributeType::Number,
                'required' => true,
                'default'  => 0
            ],
            'baseDiscount'    => [
                AttributeType::Number,
                'decimals' => 5,
                'required' => true,
                'default'  => 0
            ],
            'perItemDiscount' => [
                AttributeType::Number,
                'decimals' => 5,
                'required' => true,
                'default'  => 0
            ],
            'percentDiscount' => [
                AttributeType::Number,
                'decimals' => 5,
                'required' => true,
                'default'  => 0
            ],
            'excludeOnSale'   => [
                AttributeType::Bool,
                'required' => true,
                'default'  => 0
            ],
            'freeShipping'    => [
                AttributeType::Bool,
                'required' => true,
                'default'  => 0
            ],
            'allGroups'       => [
                AttributeType::Bool,
                'required' => true,
                'default'  => 0
            ],
            'allProducts'     => [
                AttributeType::Bool,
                'required' => true,
                'default'  => 0
            ],
            'allProductTypes' => [
                AttributeType::Bool,
                'required' => true,
                'default'  => 0
            ],
            'enabled'         => [
                AttributeType::Bool,
                'required' => true,
                'default'  => 1
            ],
        ];
    }

}