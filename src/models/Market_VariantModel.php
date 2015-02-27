<?php

namespace Craft;

use Market\Traits\Market_ModelRelationsTrait;

/**
 * Class Market_VariantModel
 *
 * @property int                 id
 * @property int                 productId
 * @property bool                isMaster
 * @property string              sku
 * @property float               price
 * @property float               width
 * @property float               height
 * @property float               length
 * @property float               weight
 * @property int                 stock
 * @property bool                unlimitedStock
 * @property int                 minQty
 * @property DateTime            deletedAt
 *
 * @property Market_ProductModel $product
 * @package Craft
 */
class Market_VariantModel extends BaseModel
{
	use Market_ModelRelationsTrait;

	public $salePrice;

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
		return UrlHelper::getCpUrl('market/products/' . $this->product->type->handle . '/' . $this->product->id . '/variants/' . $this->id);
	}

	/**
	 * @return string
	 */
	public function getOptionsText()
	{
		$optionValues = [];
		$values       = $this->getOptionValuesArray();
		foreach ($values as $key => $value) {
			$optionValues[] = "$key: $value";
		}

		return join(" ", $optionValues);
	}

	/**
	 * @param bool $idKeys Whether key and values in result should be
	 *                     identifiers
	 *
	 * @return array
	 */
	public function getOptionValuesArray($idKeys = false)
	{
		$optionValues = craft()->market_optionValue->getAllByVariantId($this->id);

		$result = [];

		foreach ($optionValues as $value) {
			$key          = $idKeys ? $value->optionType->id : $value->optionType->name;
			$result[$key] = $idKeys ? $value->id : $value->displayName;
		}

		return $result;
	}

	/**
	 * @return bool
	 */
	public function underSale()
	{
		return $this->salePrice != $this->price;
	}

	protected function defineAttributes()
	{
		return array_merge(parent::defineAttributes(), [
			'id'             => AttributeType::Number,
			'productId'      => AttributeType::Number,
			'isMaster'       => AttributeType::Bool,
			'sku'            => [AttributeType::String, 'required' => true],
			'price'          => [AttributeType::Number, 'decimals' => 4, 'required' => true],
			'width'          => [AttributeType::Number, 'decimals' => 4],
			'height'         => [AttributeType::Number, 'decimals' => 4],
			'length'         => [AttributeType::Number, 'decimals' => 4],
			'weight'         => [AttributeType::Number, 'decimals' => 4],
			'stock'          => [AttributeType::Number],
			'unlimitedStock' => [AttributeType::Bool, 'default' => 0],
			'minQty'         => AttributeType::Number,
			'deletedAt'      => [AttributeType::DateTime]
		]);
	}
}