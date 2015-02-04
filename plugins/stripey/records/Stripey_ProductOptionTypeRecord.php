<?php
namespace Craft;

/**
 * Class Stripey_ProductOptionTypeRecord
 *
 * @property int                      productId
 * @property int                      optionTypeId
 *
 * @property Stripey_ProductRecord    product
 * @property Stripey_OptionTypeRecord optionType
 * @package Craft
 */
class Stripey_ProductOptionTypeRecord extends BaseRecord
{

	public function getTableName()
	{
		return "stripey_product_optiontypes";
	}

	/**
	 * @return array
	 */
	public function defineRelations()
	{
		return array(
			'product'    => array(static::BELONGS_TO, 'Stripey_ProductRecord'),
			'optionType' => array(static::BELONGS_TO, 'Stripey_OptionTypeRecord'),
		);
	}

}