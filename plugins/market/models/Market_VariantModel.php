<?php

namespace Craft;

/**
 * Class Market_VariantModel
 *
 * @property int      id
 * @property int      productId
 * @property bool     isMaster
 * @property string   sku
 * @property float    price
 * @property float    width
 * @property float    height
 * @property float    length
 * @property float    weight
 * @property float    stock
 * @property DateTime deletedAt
 * @package Craft
 */
class Market_VariantModel extends BaseModel
{
	/**
	 * Fills the Model from the record and also fills the product model
	 *
	 * @param array|Market_VariantRecord $values
	 *
	 * @return $this
	 */

	public $product;

	public static function populateModel($values)
	{
		$model = parent::populateModel($values);
		if (is_object($values) && $values instanceof Market_VariantRecord) {
			if (is_object($model->product) && $model->product instanceof Market_ProductRecord) {
				$model->product = Market_ProductModel::populateModel($values->product);
			}
		}

		return $model;
	}

	public function isLocalized()
	{
		return false;
	}

	public function __toString()
	{
		return $this->sku;
	}

	public function getCpEditUrl()
	{
		$this->product = craft()->market_product->getById($this->productId);
		return UrlHelper::getCpUrl('market/products/' . $this->product->productType->handle . '/' . $this->product->id . '/variants/' . $this->id);
	}

	public function getOptionsText()
	{
		$productOptionTypes = $this->product->optionTypes;
		$optionValues       = array();
		foreach ($productOptionTypes as $optionType) {
			$optionValue    = $this->getOptionValue($optionType->id);
			$optionValues[] = $optionType->name . ": " . $optionValue->displayName;
		}

		return join(" ", $optionValues);
	}

	public function getOptionValue($optionTypeId)
	{
		$optionValue = Market_OptionValueRecord::model()->find(array(
			'join'      => 'JOIN craft_market_variant_optionvalues v ON v.optionValueId = t.id',
			'condition' => 'v.variantId = :v AND t.optionTypeId = :ot',
			'params'    => array('v' => $this->id, 'ot' => $optionTypeId),
		));

		return Market_OptionValueModel::populateModel($optionValue);
	}

	protected function defineAttributes()
	{
		return array_merge(parent::defineAttributes(), array(
			'id'        => AttributeType::Number,
			'productId' => AttributeType::Number,
			'isMaster'  => AttributeType::Bool,
			'sku'       => array(AttributeType::String, 'required' => true),
			'price'     => array(AttributeType::Number, 'decimals' => 4, 'required' => true),
			'width'     => array(AttributeType::Number, 'decimals' => 4),
			'height'    => array(AttributeType::Number, 'decimals' => 4),
			'length'    => array(AttributeType::Number, 'decimals' => 4),
			'weight'    => array(AttributeType::Number, 'decimals' => 4),
			'stock'     => array(AttributeType::Number),
			'deletedAt' => array(AttributeType::DateTime)
		));
	}
}