<?php

namespace Craft;
use Market\Traits\Market_ModelRelationsTrait;

/**
 * Class Market_LineItemModel
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
 * @property int taxCategoryId
 * @property string optionsJson
 *
 * @property Market_OrderRecord order
 * @property Market_VariantRecord variant
 * @property Market_TaxCategoryRecord taxCategory
 */
class Market_LineItemModel extends BaseModel
{
    use Market_ModelRelationsTrait;

	protected function defineAttributes()
	{
		return [
			'id' 			=> AttributeType::Number,
			'variantId' 	=> AttributeType::Number,
			'orderId' 		=> AttributeType::Number,
			'price' 		=> [AttributeType::Number, 'min' => 0, 'decimals' => 4, 'required' => true],
			'subtotal' 		=> [AttributeType::Number, 'min' => 0, 'decimals' => 4, 'required' => true],
			'subtotalIncTax'=> [AttributeType::Number, 'min' => 0, 'decimals' => 4, 'required' => true],
			'shipTotal' 	=> [AttributeType::Number, 'min' => 0, 'decimals' => 4, 'required' => true, 'default' => 0],
			'total' 		=> [AttributeType::Number, 'min' => 0, 'decimals' => 4, 'required' => true],
			'totalIncTax' 	=> [AttributeType::Number, 'min' => 0, 'decimals' => 4, 'required' => true],
			'qty'   		=> [AttributeType::Number, 'min' => 0, 'required' => true],
			'optionsJson'  	=> AttributeType::Mixed,
		];
	}

    /**
     * Safe getter which covers cases when a parent variant was deleted
     *
     * @return int|null
     */
    public function getTaxCategoryIdSafe()
    {
        if($this->variant) {
            return $this->variant->product->taxCategoryId;
        } else {
            return null;
        }
    }
}