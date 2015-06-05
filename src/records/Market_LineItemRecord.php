<?php

namespace Craft;

/**
 * Class Market_LineItemRecord
 *
 * @package Craft
 *
 * @property int                      id
 * @property float                    price
 * @property float                    saleAmount
 * @property float                    taxAmount
 * @property float                    shippingAmount
 * @property float                    discountAmount
 * @property float                    weight
 * @property float                    height
 * @property float                    width
 * @property float                    length
 * @property float                    total
 * @property int                      qty
 * @property string                   optionsJson
 *
 * @property int                      orderId
 * @property int                      variantId
 * @property int                      taxCategoryId
 *
 * @property Market_OrderRecord       order
 * @property Market_VariantRecord     variant
 * @property Market_TaxCategoryRecord taxCategory
 */
class Market_LineItemRecord extends BaseRecord
{
	/**
	 * @return string
	 */
	public function getTableName()
	{
		return "market_lineitems";
	}

	/**
	 * @return array
	 */
	public function defineIndexes()
	{
		return [
			['columns' => ['orderId', 'variantId'], 'unique' => true],
		];
	}

	/**
	 * @return array
	 */
	public function defineRelations()
	{
		return [
			'order'       => [static::BELONGS_TO, 'Market_OrderRecord', 'required' => true, 'onDelete' => static::CASCADE],
			'variant'     => [static::BELONGS_TO, 'Market_VariantRecord', 'onUpdate' => self::CASCADE, 'onDelete' => self::SET_NULL],
			'taxCategory' => [static::BELONGS_TO, 'Market_TaxCategoryRecord', 'onUpdate' => self::CASCADE, 'onDelete' => self::RESTRICT, 'required' => true],
		];
	}

	/**
	 * Extra qty validation
	 *
	 * @param null $attributes
	 * @param bool $clearErrors
	 *
	 * @return bool
	 */
	public function validate($attributes = NULL, $clearErrors = true)
	{
		$result = parent::validate($attributes, $clearErrors);
		if (!$result) {
			return false;
		}

		$variant = $this->variant;

		if (!$variant->unlimitedStock && $this->qty > $variant->stock) {
			$error = sprintf('There are only %d items left in stock', $variant->stock);
			$this->addError('qty', $error);
		}

		if ($this->qty < $variant->minQty) {
			$error = sprintf('Minimal order qty for this variant is %d', $variant->minQty);
			$this->addError('qty', $error);
		}

		return $this->hasErrors();
	}

	/**
	 * @return array
	 */
	protected function defineAttributes()
	{
		return [
			'price'          => [AttributeType::Number, 'min' => 0, 'decimals' => 4, 'required' => true],
			'saleAmount'     => [AttributeType::Number, 'decimals' => 4, 'required' => true, 'default' => 0],
			'taxAmount'      => [AttributeType::Number, 'decimals' => 4, 'required' => true, 'default' => 0],
			'shippingAmount' => [AttributeType::Number, 'min' => 0, 'decimals' => 4, 'required' => true, 'default' => 0],
			'discountAmount' => [AttributeType::Number, 'decimals' => 4, 'required' => true, 'default' => 0],
			'weight'         => [AttributeType::Number, 'min' => 0, 'decimals' => 4, 'required' => true, 'default' => 0],
			'height'         => [AttributeType::Number, 'min' => 0, 'decimals' => 4, 'required' => true, 'default' => 0],
			'length'         => [AttributeType::Number, 'min' => 0, 'decimals' => 4, 'required' => true, 'default' => 0],
			'width'          => [AttributeType::Number, 'min' => 0, 'decimals' => 4, 'required' => true, 'default' => 0],
			'total'          => [AttributeType::Number, 'min' => 0, 'decimals' => 4, 'required' => true, 'default' => 0],
			'qty'            => [AttributeType::Number, 'min' => 0, 'required' => true],
			'optionsJson'    => [AttributeType::Mixed, 'required' => true],

			'taxCategoryId'  => [AttributeType::Number, 'required' => true],
		];
	}
}