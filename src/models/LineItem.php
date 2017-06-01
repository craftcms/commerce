<?php

namespace craft\commerce\models;

use craft\commerce\base\Element;
use craft\commerce\base\Model;
use craft\commerce\base\Purchasable;
use craft\commerce\elements\Order;
use craft\commerce\helpers\Currency;
use craft\commerce\records\TaxRate as TaxRateRecord;

/**
 * Line Item model representing a line item on an order.
 *
 * @package   Craft
 *
 * @property int                                     $id
 * @property float                                   $price
 * @property float                                   $saleAmount
 * @property float                                   $salePrice
 * @property float                                   $tax
 * @property float                                   $taxIncluded
 * @property float                                   $shippingCost
 * @property float                                   $discount
 * @property float                                   $weight
 * @property float                                   $height
 * @property float                                   $width
 * @property float                                   $length
 * @property float                                   $total
 * @property int                                     $qty
 * @property string                                  $note
 * @property string                                  $snapshot
 *
 * @property int                                     $orderId
 * @property int                                     $purchasableId
 * @property string                                  $optionsSignature
 * @property mixed                                   $options
 * @property int                                     $taxCategoryId
 * @property int                                     $shippingCategoryId
 *
 * @property bool                                    $onSale
 * @property Purchasable                             $purchasable
 *
 * @property \craft\commerce\elements\Order          $order
 * @property \craft\commerce\models\TaxCategory      $taxCategory
 * @property \craft\commerce\models\ShippingCategory $shippingCategory
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.models
 * @since     1.0
 */
class LineItem extends Model
{

    /**
     * @var int ID
     */
    public $id;

    /**
     * @var mixed Options
     */
    public $options;

    /**
     * @var string Options Signature Hash
     */
    public $optionsSignature;

    /**
     * @var float Price
     */
    public $price = 0;

    /**
     * @var float Sale amount
     */
    public $saleAmount = 0;

    /**
     * @var float Sale price
     */
    public $salePrice = 0;

    /**
     * @var float tax
     */
    public $tax = 0;

    /**
     * @var float Tax included amount
     */
    public $taxIncluded = 0;

    /**
     * @var float Shipping Cost
     */
    public $shippingCost = 0;

    /**
     * @var float Discount
     */
    public $discount = 0;

    /**
     * @var float Weight
     */
    public $weight = 0;

    /**
     * @var float Length
     */
    public $length = 0;

    /**
     * @var float Height
     */
    public $height = 0;

    /**
     * @var float Width
     */
    public $width = 0;

    /**
     * @var float Total
     */
    public $total = 0;

    /**
     * @var int Quantity
     */
    public $qty;

    /**
     * @var mixed Snapshot
     */
    public $snapshot;

    /**
     * @var string Note
     */
    public $note;

    /**
     * @var int Purchasable ID
     */
    public $purchasableId;

    /**
     * @var int Order ID
     */
    public $orderId;

    /**
     * @var int Tax category ID
     */
    public $taxCategoryId;

    /**
     * @var int Shipping category ID
     */
    public $shippingCategoryId;

    /**
     * @var mixed Date Created
     */
    public $dateCreated;

    /**
     * @var mixed Date Updated
     */
    public $dateUpdated;

    /**
     * @var string Unique ID
     */
    public $uid;

    /**
     * @var \craft\commerce\base\PurchasableInterface Purchasable
     */
    private $_purchasable;

    /**
     * @var \craft\commerce\elements\Order Order
     */
    private $_order;

    /**
     * @return \craft\commerce\elements\Order|null
     */
    public function getOrder()
    {
        if (!$this->_order) {
            $this->_order = Plugin::getInstance()->getOrders()->getOrderById($this->orderId);
        }

        return $this->_order;
    }

    /**
     * @param \craft\commerce\elements\Order $order
     */
    public function setOrder(Order $order)
    {
        $this->orderId = $order->id;
        $this->_order = $order;
    }

    public function rules()
    {
        return [
            [
                [
                    'optionsSignature',
                    'price',
                    'saleAmount',
                    'salePrice',
                    'tax',
                    'taxIncluded',
                    'shippingCost',
                    'discount',
                    'weight',
                    'length',
                    'height',
                    'width',
                    'total',
                    'qty',
                    'snapshot',
                    'taxCategoryId',
                    'shippingCategoryId'
                ], 'required'
            ]
        ];
    }

    /**
     * @return float
     */
    public function getSubtotal()
    {
        // The subtotal should always be rounded.
        return $this->qty * Currency::round($this->salePrice);
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
     * @param TaxRate ::taxables
     *
     * @return int
     */
    public function getTaxableSubtotal($taxable)
    {
        switch ($taxable) {
            case TaxRateRecord::TAXABLE_PRICE:
                $taxableSubtotal = $this->getSubtotal() + $this->discount;
                break;
            case TaxRateRecord::TAXABLE_SHIPPING:
                $taxableSubtotal = $this->shippingCost;
                break;
            case TaxRateRecord::TAXABLE_PRICE_SHIPPING:
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

        if ($this->qty <= 0 && $this->id) {
            return false;
        }

        /* @var $purchasable Purchasable */
        $purchasable = $this->getPurchasable();
        if (!$purchasable || !$purchasable->getIsAvailable()) {
            return false;
        }

        $this->fillFromPurchasable($purchasable);

        return true;
    }

    /**
     * @return \craft\commerce\base\Element|null
     */
    public function getPurchasable()
    {
        if (!$this->_purchasable) {
            $this->_purchasable = Craft::$app->getElements()->getElementById($this->purchasableId);
        }

        return $this->_purchasable;
    }

    /**
     * @param \craft\commerce\base\Element $purchasable
     *
     * @return void
     */
    public function setPurchasable(Element $purchasable)
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
            'price' => $purchasable->getPrice(),
            'sku' => $purchasable->getSku(),
            'description' => $purchasable->getDescription(),
            'purchasableId' => $purchasable->getPurchasableId(),
            'cpEditUrl' => '#',
            'options' => $this->options
        ];

        // Add our purchasable data to the snapshot, save our sales.
        $this->snapshot = array_merge($purchasable->getSnapshot(), $snapshot);

        $purchasable->populateLineItem($this);

        //raising onPopulate event
        $event = new Event($this, [
            'lineItem' => $this,
            'purchasable' => $this->purchasable
        ]);
        Plugin::getInstance()->getLineItems()->onPopulateLineItem($event);

        // Always make sure salePrice is equal to the price and saleAmount
        $this->salePrice = Currency::round($this->saleAmount + $this->price);
    }

    /**
     * @return bool
     */
    public function getOnSale()
    {
        return null === $this->salePrice ? false : ($this->salePrice != $this->price);
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
     * @return \craft\commerce\models\TaxCategory|null
     */
    public function getTaxCategory()
    {
        return Plugin::getInstance()->getTaxCategories()->getTaxCategoryById($this->taxCategoryId);
    }

    /**
     * @return \craft\commerce\models\TaxCategory|null
     */
    public function getShippingCategory()
    {
        return Plugin::getInstance()->getShippingCategories()->getShippingCategoryById($this->shippingCategoryId);
    }

}
