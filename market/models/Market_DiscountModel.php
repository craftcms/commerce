<?php

namespace Craft;

use Market\Traits\Market_ModelRelationsTrait;

/**
 * Class Market_DiscountModel
 *
 * @property int                       id
 * @property string                    name
 * @property string                    description
 * @property string                    code
 * @property int                       perUserLimit
 * @property int                       totalUseLimit
 * @property int                       totalUses
 * @property DateTime                  dateFrom
 * @property DateTime                  dateTo
 * @property int                       purchaseTotal
 * @property int                       purchaseQty
 * @property float                     baseDiscount
 * @property float                     perItemDiscount
 * @property float                     percentDiscount
 * @property bool                      excludeOnSale
 * @property bool                      freeShipping
 * @property bool                      allGroups
 * @property bool                      allProducts
 * @property bool                      allProductTypes
 * @property bool                      enabled
 *
 * @property Market_ProductModel[]     products
 * @property Market_ProductTypeModel[] productTypes
 * @property UserGroupModel[]          groups
 * @package Craft
 */
class Market_DiscountModel extends BaseModel
{
    use Market_ModelRelationsTrait;

    /**
     * @return array
     */
    public function getGroupsIds()
    {
        return array_map(function ($group) {
            return $group->id;
        }, $this->groups);
    }

    /**
     * @return array
     */
    public function getProductTypesIds()
    {
        return array_map(function ($type) {
            return $type->id;
        }, $this->productTypes);
    }

    /**
     * @return array
     */
    public function getProductsIds()
    {
        return array_map(function ($product) {
            return $product->id;
        }, $this->products);
    }

    protected function defineAttributes()
    {
        return [
            'id'              => AttributeType::Number,
            'name'            => [AttributeType::Name, 'required' => true],
            'code'            => [AttributeType::String, 'required' => true],
            'perUserLimit'    => [AttributeType::Number, 'default' => 0],
            'totalUseLimit'   => [AttributeType::Number, 'default' => 0],
            'totalUses'       => [AttributeType::Number, 'default' => 0],
            'description'     => AttributeType::Mixed,
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