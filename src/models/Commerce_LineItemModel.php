<?php
namespace Craft;

use Commerce\Helpers\CommerceCurrencyHelper;
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
 * @property int                       $shippingCategoryId
 *
 * @property bool                      $onSale
 * @property Purchasable               $purchasable
 *
 * @property Commerce_OrderModel       $order
 * @property Commerce_TaxCategoryModel $taxCategory
 * @property Commerce_ShippingCategoryModel $shippingCategory
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
        $this->orderId = $order->id;
        $this->_order = $order;
    }

    /**
     * @return float
     */
    public function getSubtotal()
    {
        // The subtotal should always be rounded.
        return $this->qty * CommerceCurrencyHelper::round($this->salePrice);
    }

    /**
     * @deprecated You should now use getSubtotal()
     * @return float
     */
    public function getSubtotalWithSale()
    {
        craft()->deprecator->log('Commerce_LineItemModel::getSubtotalWithSale():removed', 'You should no longer use `lineItem.subtotalWithSale` for the line item’s subtotal. Use `lineItem.subtotal`. Same goes for $lineItem->getSubtotalWithSale() in PHP.');

        return $this->getSubtotal();
    }

    /**
     * Returns the Purchasable’s sale price multiplied by the quantity of the line item.
     *
     * @return float
     */
    public function getTotal()
    {
        return $this->getSubtotal() + $this->tax + $this->discount + $this->shippingCost;
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
                $taxableSubtotal = $this->getSubtotal() + $this->discount;
                break;
            case Commerce_TaxRateRecord::TAXABLE_SHIPPING:
                $taxableSubtotal = $this->shippingCost;
                break;
            case Commerce_TaxRateRecord::TAXABLE_PRICE_SHIPPING:
                $taxableSubtotal = $this->getSubtotal() + $this->discount + $this->shippingCost;
                break;
            default:
                // default to just price
                $taxableSubtotal = $this->getSubtotal() + $this->discount;
        }

        return $taxableSubtotal;
    }

    /**
     * @return bool False when no related purchasable exists or order complete.
     */
    public function refreshFromPurchasable()
    {

        if ($this->qty <= 0 && $this->id)
        {
            return false;
        }

        /* @var $purchasable Purchasable */
        $purchasable = $this->getPurchasable();
        if (!$purchasable || !$purchasable->getIsAvailable())
        {
            return false;
        }

        $this->fillFromPurchasable($purchasable);

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
        $this->shippingCategoryId = $purchasable->getShippingCategoryId();

        // Since sales cannot apply to non core purchasables yet, set to price at default
        $this->salePrice = $purchasable->getPrice();
        $this->saleAmount = 0;

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

        $purchasable->populateLineItem($this);

        //raising onPopulate event
        $event = new Event($this, [
            'lineItem' => $this,
            'purchasable' => $this->purchasable
        ]);
        craft()->commerce_lineItems->onPopulateLineItem($event);

        // Always make sure salePrice is equal to the price and saleAmount
        $this->salePrice = CommerceCurrencyHelper::round($this->saleAmount + $this->price);
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
        return null === $this->salePrice ? false : (CommerceCurrencyHelper::round($this->salePrice) != CommerceCurrencyHelper::round($this->price));
    }

    /**
     * Returns the description from the snapshot of the purchasable
     */
    public function getDescription()
    {
        return HtmlHelper::decode($this->snapshot['description']);
    }

    /**
     * Returns the description from the snapshot of the purchasable
     */
    public function getSku()
    {
        return HtmlHelper::decode($this->snapshot['sku']);
    }

    /**
     * @return Commerce_TaxCategoryModel|null
     */
    public function getTaxCategory()
    {
        return craft()->commerce_taxCategories->getTaxCategoryById($this->taxCategoryId);
    }

    /**
     * @return Commerce_TaxCategoryModel|null
     */
    public function getShippingCategory()
    {
        return craft()->commerce_shippingCategories->getShippingCategoryById($this->shippingCategoryId);
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
            'shippingCategoryId'    => [AttributeType::Number, 'required' => true],
        ];
    }
}
