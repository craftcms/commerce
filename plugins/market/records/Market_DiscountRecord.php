<?php

namespace Craft;

/**
 * Class Market_DiscountRecord
 *
 * @property int        id
 * @property string     name
 * @property string     description
 * @property DateTime   dateFrom
 * @property DateTime   dateTo
 * @property int        purchaseTotal
 * @property int        purchaseQty
 * @property float      baseDiscount
 * @property float      perItemDiscount
 * @property float      percentDiscount
 * @property bool       freeShipping
 * @property bool       enabled
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
			'groups'       => [static::MANY_MANY, 'UserGroupRecord', 'market_discount_usergroups(discountId, userGroupId)'],
			'products'     => [static::MANY_MANY, 'Market_ProductRecord', 'market_discount_products(discountId, productId)'],
			'productTypes' => [static::MANY_MANY, 'Market_ProductTypeRecord', 'market_discount_producttypes(discountId, productTypeId)'],
		];
	}

	protected function defineAttributes()
	{
		return [
			'name'              => [AttributeType::Name, 'required' => true],
			'description'       => AttributeType::Mixed,
            'dateFrom'          => AttributeType::DateTime,
            'dateTo'            => AttributeType::DateTime,
            'purchaseTotal'     => [AttributeType::Number, 'required' => true, 'default' => 0],
            'purchaseQty'       => [AttributeType::Number, 'required' => true, 'default' => 0],
            'baseDiscount'      => [AttributeType::Number, 'decimals' => 5, 'required' => true, 'default' => 0],
            'perItemDiscount'   => [AttributeType::Number, 'decimals' => 5, 'required' => true, 'default' => 0],
            'percentDiscount'   => [AttributeType::Number, 'decimals' => 5, 'required' => true, 'default' => 0],
            'freeShipping'      => [AttributeType::Bool, 'required' => true, 'default' => 0],
            'enabled'           => [AttributeType::Bool, 'required' => true, 'default' => 1],
		];
	}

}