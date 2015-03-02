<?php
namespace Craft;

/**
 * Class Market_ProductOptionTypeRecord
 *
 * @property int                      productId
 * @property int                      optionTypeId
 *
 * @property Market_ProductRecord     product
 * @property Market_OptionTypeRecord  optionType
 * @package Craft
 */
class Market_ProductOptionTypeRecord extends BaseRecord
{

	public function getTableName()
	{
		return "market_product_optiontypes";
	}

	/**
	 * @return array
	 */
	public function defineRelations()
	{
		return [
			'product'    => [static::BELONGS_TO, 'Market_ProductRecord'],
			'optionType' => [static::BELONGS_TO, 'Market_OptionTypeRecord'],
		];
	}

}