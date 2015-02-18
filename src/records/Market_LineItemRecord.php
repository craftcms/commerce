<?php

namespace Craft;

/**
 * Class Market_LineItemRecord
 * @package Craft
 *
 * @property int id
 * @property float price
 * @property float subtotal
 * @property float subtotalIncTax
 * @property float shipTotal
 * @property float total
 * @property float totalIncTax
 * @property int qty
 * @property int orderId
 * @property int variantId
 * @property string optionsJson
 *
 * @property Market_OrderRecord order
 * @property Market_VariantRecord variant
 */
class Market_LineItemRecord extends BaseRecord
{
	/**
	 * Returns the name of the associated database table.
	 *
	 * @return string
	 */
	public function getTableName()
	{
		return "market_lineitems";
	}

	public function defineIndexes()
	{
		return [
			['columns' => ['orderId', 'variantId'], 'unique' => true],
		];
	}

	protected function defineAttributes()
	{
		return [
			'price' 		=> [AttributeType::Number, 'min' => 0, 'decimals' => 4, 'required' => true],
			'subtotal' 		=> [AttributeType::Number, 'min' => 0, 'decimals' => 4, 'required' => true, 'default' => 0],
			'subtotalIncTax'=> [AttributeType::Number, 'min' => 0, 'decimals' => 4, 'required' => true, 'default' => 0],
			'shipTotal' 	=> [AttributeType::Number, 'min' => 0, 'decimals' => 4, 'required' => true, 'default' => 0],
			'total' 		=> [AttributeType::Number, 'min' => 0, 'decimals' => 4, 'required' => true, 'default' => 0],
			'totalIncTax' 	=> [AttributeType::Number, 'min' => 0, 'decimals' => 4, 'required' => true, 'default' => 0],
			'qty'   		=> [AttributeType::Number, 'min' => 0, 'required' => true],
			'optionsJson'  	=> [AttributeType::Mixed, 'required' => true],
		];
	}

	public function defineRelations()
	{
		return [
			'order' => [static::BELONGS_TO, 'Market_OrderRecord', 'required' => true, 'onDelete' => static::CASCADE],
			'variant' => [static::BELONGS_TO, 'Market_VariantRecord', 'onUpdate' => self::CASCADE, 'onDelete' => self::SET_NULL],
		];
	}

	/**
	 * Extra qty validation
	 *
	 * @param null $attributes
	 * @param bool $clearErrors
	 * @return bool
	 */
	public function validate($attributes = null, $clearErrors = true)
	{
		$result = parent::validate($attributes, $clearErrors);
		if(!$result) {
			return false;
		}

		$variant = $this->variant;

		if(!$variant->unlimitedStock && $this->qty > $variant->stock) {
			$error = sprintf('There are only %d items left in stock', $variant->stock);
			$this->addError('qty', $error);
		}

		if ($this->qty < $variant->minQty) {
			$error = sprintf('Minimal order qty for this variant is %d', $variant->minQty);
			$this->addError('qty', $error);
		}

		return $this->hasErrors();
	}
	public function recalculate()
	{
		$this->subtotal = $this->price * $this->qty;
		$this->subtotalIncTax = $this->subtotal; //@TODO calculate tax by default zone or shipment address
		$this->total = $this->subtotal + $this->shipTotal;
		$this->totalIncTax = $this->subtotalIncTax + $this->shipTotal;
	}

	protected function beforeSave()
	{
		$this->recalculate();
		return parent::beforeSave();
	}
}