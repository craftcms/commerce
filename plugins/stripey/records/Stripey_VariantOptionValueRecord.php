<?php
namespace Craft;

/**
 * Class Stripey_VariantOptionValueRecord
 *
 * @property int                       variantId
 * @property int                       optionValueId
 *
 * @property Stripey_VariantRecord     variant
 * @property Stripey_OptionValueRecord optionValue
 * @package Craft
 */
class Stripey_VariantOptionValueRecord extends BaseRecord
{

	public function getTableName()
	{
		return "stripey_variant_optionvalues";
	}

	/**
	 * @return array
	 */
	public function defineRelations()
	{
		return array(
			'variant'     => array(static::BELONGS_TO, 'Stripey_VariantRecord', 'required' => true, 'onUpdate' => self::CASCADE, 'onDelete' => self::RESTRICT),
			'optionValue' => array(static::BELONGS_TO, 'Stripey_OptionValueRecord', 'required' => true, 'onUpdate' => self::CASCADE, 'onDelete' => self::RESTRICT),
		);
	}
}