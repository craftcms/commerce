<?php
namespace Craft;

use Commerce\Interfaces\Purchasable;

/**
 * Line Item model representing a line item on an order.
 *
 * @package   Craft
 *
 * @property int                       $id
 * @property float                     $price
 * @property float                     $saleAmount
 * @property float                     $salePrice
 * @property float                     $tax
 * @property float                     $taxIncluded
 * @property float                     $shippingCost
 * @property float                     $discount
 * @property float                     $weight
 * @property float                     $height
 * @property float                     $width
 * @property float                     $length
 * @property float                     $total
 * @property int                       $qty
 * @property string                    $note
 * @property string                    $snapshot
 *
 * @property int                       $orderId
 * @property int                       $purchasableId
 * @property string                    $optionsSignature
 * @property mixed                     $options
 * @property int                       $taxCategoryId
 *
 * @property bool                      $onSale
 * @property Purchasable               $purchasable
 *
 * @property Commerce_OrderModel       $order
 * @property Commerce_TaxCategoryModel $taxCategory
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.models
 * @since     1.0
 */
class Commerce_LineItemModel extends BaseModel
{
	private $_purchasable;

	private $_order;

	/**
	 * @return Commerce_OrderModel|null
	 */
	public function getOrder()
	{
		if (!$this->_order)
		{
			$this->_order = craft()->commerce_orders->getOrderById($this->orderId);
		}

		return $this->_order;
	}

	/**
	 * @param Commerce_OrderModel $order
	 */
	public function setOrder(Commerce_OrderModel $order)
	{
		$this->_order = $order;
	}

	/**
	 * @return int
	 */
	public function getSubtotalWithSale()
	{
		return $this->qty * ($this->price + $this->saleAmount);
	}

	/**
	 * @return int
	 */
	public function getTotal()
	{
		return $this->getSubtotalWithSale() + $this->tax + $this->discount + $this->shippingCost;
	}

	/**
	 * @param Commerce_TaxRateRecord ::taxables
	 *
	 * @return int
	 */
	public function getTaxableSubtotal($taxable)
	{
		switch ($taxable)
		{
			case Commerce_TaxRateRecord::TAXABLE_PRICE:
				$taxableSubtotal = $this->getSubtotalWithSale() + $this->discount;
				break;
			case Commerce_TaxRateRecord::TAXABLE_SHIPPING:
				$taxableSubtotal = $this->shippingCost;
				break;
			case Commerce_TaxRateRecord::TAXABLE_PRICE_SHIPPING:
				$taxableSubtotal = $this->getSubtotalWithSale() + $this->discount + $this->shippingCost;
				break;
			default:
				// default to just price
				$taxableSubtotal = $this->getSubtotalWithSale() + $this->discount;
		}

		return $taxableSubtotal;
	}

	/**
	 * @return bool False when no related purchasable exists or order complete.
	 */
	public function refreshFromPurchasable()
	{
		if (!$purchasable = $this->getPurchasable())
		{
			return false;
		}

		if ($this->qty <= 0 && $this->id)
		{
			return false;
		}

		// TODO move the 'availability' to the purchasable interface
		if ($purchasable instanceof Commerce_VariantModel)
		{
			// remove the item from the cart if the purchasable is a variant and not enabled
			if ($purchasable->getStatus() != BaseElementModel::ENABLED)
			{
				return false;
			}

			if ($purchasable->stock < 1 && !$purchasable->unlimitedStock)
			{
				return false;
			}
		}


		$this->fillFromPurchasable($this->purchasable);

		return true;
	}

	/**
	 * @return BaseElementModel|null
	 */
	public function getPurchasable()
	{
		if (!$this->_purchasable)
		{
			$this->_purchasable = craft()->elements->getElementById($this->purchasableId);
		}

		return $this->_purchasable;
	}

	/**
	 * @param BaseElementModel $purchasable
	 *
	 * @return void
	 */
	public function setPurchasable(BaseElementModel $purchasable)
	{
		$this->_purchasable = $purchasable;
	}

	/**
	 * @param Purchasable $purchasable
	 */
	public function fillFromPurchasable(Purchasable $purchasable)
	{
		$this->price = $purchasable->getPrice();
		$this->taxCategoryId = $purchasable->getTaxCategoryId();

		// Since sales cannot apply to non core purchasables, set to price at default
		$this->salePrice = $purchasable->getPrice();

		$snapshot = [
			'price'         => $purchasable->getPrice(),
			'sku'           => $purchasable->getSku(),
			'description'   => $purchasable->getDescription(),
			'purchasableId' => $purchasable->getPurchasableId(),
			'cpEditUrl'     => '#',
			'options'       => $this->options
		];

		// Add our purchasable data to the snapshot, save our sales.
		$this->snapshot = array_merge($purchasable->getSnapShot(), $snapshot);

		if ($purchasable instanceof Commerce_VariantModel)
		{

			$this->weight = $purchasable->weight * 1; //converting nulls
			$this->height = $purchasable->height * 1; //converting nulls
			$this->length = $purchasable->length * 1; //converting nulls
			$this->width = $purchasable->width * 1; //converting nulls

			$sales = craft()->commerce_sales->getSalesForVariant($purchasable);

			foreach ($sales as $sale)
			{
				$this->saleAmount += $sale->calculateTakeoff($this->price);
			}

			// Don't let sale amount be more than the price.
			if (-$this->saleAmount > $this->price)
			{
				$this->saleAmount = -$this->price;
			}

			// If the product is not promotable but has saleAmount, reset saleAmount to zero
			if (!$purchasable->product->promotable && $this->saleAmount)
			{
				$this->saleAmount = 0;
			}

			$this->salePrice = $this->saleAmount + $this->price;
		}
		else
		{
			// Non core commerce purchasables cannot have sales applied (yet)
			$this->saleAmount = 0;
		}
	}

	/**
	 * @return bool
	 */
	public function getUnderSale()
	{
		craft()->deprecator->log('Commerce_LineItemModel::underSale():removed', 'You should no longer use `underSale` on the lineItem. Use `onSale`.');

		return $this->getOnSale();
	}

	/**
	 * @return bool
	 */
	public function getOnSale()
	{
		return is_null($this->salePrice) ? false : ($this->salePrice != $this->price);
	}

	/**
	 * Returns the description from the snapshot of the purchasable
	 */
	public function getDescription()
	{
		return $this->snapshot['description'];
	}

	/**
	 * Returns the description from the snapshot of the purchasable
	 */
	public function getSku()
	{
		return $this->snapshot['sku'];
	}

	/**
	 * @return Commerce_TaxCategoryModel|null
	 */
	public function getTaxCategory()
	{
		return craft()->commerce_taxCategories->getTaxCategoryById($this->taxCategoryId);
	}

	/**
	 * @return array
	 */
	protected function defineAttributes()
	{
		return [
			'id'               => AttributeType::Number,
			'options'          => AttributeType::Mixed,
			'optionsSignature' => [AttributeType::String, 'required' => true],
			'price'            => [
				AttributeType::Number,
				'min'      => 0,
				'decimals' => 4,
				'required' => true
			],
			'saleAmount'       => [
				AttributeType::Number,
				'decimals' => 4,
				'required' => true,
				'default'  => 0
			],
			'salePrice'        => [
				AttributeType::Number,
				'decimals' => 4,
				'required' => true,
				'default'  => 0
			],
			'tax'              => [
				AttributeType::Number,
				'decimals' => 4,
				'required' => true,
				'default'  => 0
			],
			'taxIncluded'      => [
				AttributeType::Number,
				'decimals' => 4,
				'required' => true,
				'default'  => 0
			],
			'shippingCost'     => [
				AttributeType::Number,
				'min'      => 0,
				'decimals' => 4,
				'required' => true,
				'default'  => 0
			],
			'discount'         => [
				AttributeType::Number,
				'decimals' => 4,
				'required' => true,
				'default'  => 0
			],
			'weight'           => [
				AttributeType::Number,
				'min'      => 0,
				'decimals' => 4,
				'required' => true,
				'default'  => 0
			],
			'length'           => [
				AttributeType::Number,
				'min'      => 0,
				'decimals' => 4,
				'required' => true,
				'default'  => 0
			],
			'height'           => [
				AttributeType::Number,
				'min'      => 0,
				'decimals' => 4,
				'required' => true,
				'default'  => 0
			],
			'width'            => [
				AttributeType::Number,
				'min'      => 0,
				'decimals' => 4,
				'required' => true,
				'default'  => 0
			],
			'total'            => [
				AttributeType::Number,
				'min'      => 0,
				'decimals' => 4,
				'required' => true,
				'default'  => 0
			],
			'qty'              => [
				AttributeType::Number,
				'min'      => 0,
				'required' => true
			],
			'snapshot'         => [AttributeType::Mixed, 'required' => true],
			'note'             => AttributeType::Mixed,
			'purchasableId'    => AttributeType::Number,
			'orderId'          => AttributeType::Number,
			'taxCategoryId'    => [AttributeType::Number, 'required' => true],
		];
	}
}
