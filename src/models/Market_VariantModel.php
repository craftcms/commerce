<?php

namespace Craft;

use Market\Traits\Market_ModelRelationsTrait;
use Market\Interfaces\Purchasable;
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

class Market_VariantModel extends BaseElementModel implements Purchasable
{
	use Market_ModelRelationsTrait;

	protected $elementType = 'Market_Variant';
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
	 * @return bool
	 */
	public function getOnSale()
	{
		return is_null($this->salePrice) ? false : ($this->salePrice != $this->price);
	}

	/**
	 * @return FieldLayoutModel|null
	 */
	public function getFieldLayout()
	{
		if ($this->productId) {
			return craft()->market_product->getById($this->productId)->getFieldLayout();
		}

		return NULL;
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

	public function getPurchasablePrice()
	{
		return $this->price;
	}

	public function getPurchasableSku()
	{
		return $this->sku;
	}

	public function getPurchasableDescription()
	{
		return $this->sku;
	}


	// events
	public function canPurchase(Market_OrderModel $order){
		return true;
	}

	public function onAddToOrder(Market_OrderModel $order){
		return true;
	}

	public function onRemoveFromOrder(Market_OrderModel $order)
	{
		return true;
	}

	public function onOrderCompleted(Market_OrderModel $order)
	{
		return true;
	}
	
}