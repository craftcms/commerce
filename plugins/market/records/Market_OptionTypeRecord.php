<?php

namespace Craft;

/**
 * Class Market_OptionTypeRecord
 *
 * @property int                         id
 * @property string                      name
 * @property string                      handle
 *
 * @property Market_ProductRecord[]     $products
 * @property Market_OptionValueRecord[] optionValues
 * @package Craft
 */
class Market_OptionTypeRecord extends BaseRecord
{
	public function getTableName()
	{
		return 'market_optiontypes';
	}

	public function defineRelations()
	{
		return array(
			'products'     => array(static::MANY_MANY, 'Market_ProductRecord', 'market_product_optiontypes(productId, optionTypeId)'),
			'optionValues' => array(static::HAS_MANY, 'Market_OptionValueRecord', 'optionTypeId'),
		);
	}

	protected function defineAttributes()
	{
		return array(
			'name'   => array(AttributeType::Name, 'required' => true),
			'handle' => array(AttributeType::Handle, 'required' => true)
		);
	}

}