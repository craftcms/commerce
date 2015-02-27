<?php
namespace Craft;

/**
 * Class Market_VariantOptionValueRecord
 *
 * @property int                       variantId
 * @property int                       optionValueId
 *
 * @property Market_VariantRecord      variant
 * @property Market_OptionValueRecord  optionValue
 * @package Craft
 */
class Market_VariantOptionValueRecord extends BaseRecord
{

	public function getTableName()
	{
		return "market_variant_optionvalues";
	}

	/**
	 * @return array
	 */
	public function defineRelations()
	{
		return array(
			'variant'     => array(static::BELONGS_TO, 'Market_VariantRecord', 'required' => true, 'onUpdate' => self::CASCADE, 'onDelete' => self::RESTRICT),
			'optionValue' => array(static::BELONGS_TO, 'Market_OptionValueRecord', 'required' => true, 'onUpdate' => self::CASCADE, 'onDelete' => self::RESTRICT),
		);
	}
}