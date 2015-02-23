<?php

namespace Craft;
use Market\Traits\Market_ModelRelationsTrait;

/**
 * Class Market_SaleModel
 *
 * @property int        id
 * @property string     name
 * @property string     description
 * @property DateTime   dateFrom
 * @property DateTime   dateTo
 * @property string     discountType
 * @property float      discountAmount
 * @property bool       allGroups
 * @property bool       allProducts
 * @property bool       allProductTypes
 * @property bool       enabled
 *
 * @property Market_ProductModel[]     products
 * @property Market_ProductTypeModel[] productTypes
 * @property UserGroupModel[]          groups
 * @package Craft
 */
class Market_SaleModel extends BaseModel
{
    use Market_ModelRelationsTrait;

	protected function defineAttributes()
	{
		return [
            'id'                => AttributeType::Number,
			'name'              => AttributeType::Name,
			'description'       => AttributeType::Mixed,
            'dateFrom'          => AttributeType::DateTime,
            'dateTo'            => AttributeType::DateTime,
            'discountType'      => AttributeType::Enum,
            'discountAmount'    => AttributeType::Number,
            'allGroups'         => [AttributeType::Bool, 'required' => true, 'default' => 0],
            'allProducts'       => [AttributeType::Bool, 'required' => true, 'default' => 0],
            'allProductTypes'   => [AttributeType::Bool, 'required' => true, 'default' => 0],
            'enabled'           => AttributeType::Bool,
		];
	}

    /**
     * @return array
     */
    public function getGroupsIds()
    {
        return array_map(function($group) {
            return $group->id;
        }, $this->groups);
    }

    /**
     * @return array
     */
    public function getProductTypesIds()
    {
        return array_map(function($type) {
            return $type->id;
        }, $this->productTypes);
    }

    /**
     * @return array
     */
    public function getProductsIds()
    {
        return array_map(function($product) {
            return $product->id;
        }, $this->products);
    }

    /**
     * @param float $price
     * @return float
     * @throws Exception
     */
    public function calculateTakeoff($price)
    {
        if($this->discountType == Market_SaleRecord::TYPE_FLAT) {
            $takeOff = $this->discountAmount;
        } else {
            $takeOff = $this->discountAmount * $price;
        }

        return $takeOff;
    }
}