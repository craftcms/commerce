<?php
namespace Craft;

use Commerce\Traits\Commerce_ModelRelationsTrait;
use Omnipay\Common\Currency;

/**
 * Order or Cart model.
 *
 * @property int $id
 * @property string $number
 * @property string $couponCode
 * @property float $itemTotal
 * @property float $totalPrice
 * @property float $totalPaid
 * @property float $baseDiscount
 * @property float $baseShippingCost
 * @property string $email
 * @property DateTime $dateOrdered
 * @property string $currency
 * @property DateTime $datePaid
 * @property string $lastIp
 * @property string $message
 * @property string $returnUrl
 * @property string $cancelUrl
 *
 * @property int $billingAddressId
 * @property mixed $billingAddressData
 * @property int $shippingAddressId
 * @property mixed $shippingAddressData
 * @property int $shippingMethod
 * @property int $paymentMethodId
 * @property int $customerId
 * @property int $orderStatusId
 *
 * @property int $totalQty
 * @property int $totalWeight
 * @property int $totalHeight
 * @property int $totalLength
 * @property int $totalWidth
 * @property int $totalTax
 * @property int $totalShippingCost
 * @property string $pdfUrl
 *
 * @property Commerce_OrderSettingsModel $type
 * @property Commerce_LineItemModel[] $lineItems
 * @property Commerce_AddressModel $billingAddress
 * @property Commerce_CustomerModel $customer
 * @property Commerce_AddressModel $shippingAddress
 * @property Commerce_OrderAdjustmentModel[] $adjustments
 * @property Commerce_PaymentMethodModel $paymentMethod
 * @property Commerce_TransactionModel[] $transactions
 * @property Commerce_OrderStatusModel $orderStatus
 * @property Commerce_OrderHistoryModel[] $histories
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.models
 * @since     1.0
 */
class Commerce_OrderModel extends BaseElementModel
{
    use Commerce_ModelRelationsTrait;

    /**
     * @var string
     */
    protected $elementType = 'Commerce_Order';

    /**
     * @var
     */
    private $_shippingAddress;

    /**
     * @var
     */
    private $_billingAddress;

    /**
     * @var array
     */
    private $_lineItems;

    /**
     * @var array
     */
    private $_orderAdjustments;

    /**
     * @return bool
     */
    public function isEditable()
    {
        // TODO: Replace with an order permission check when we have one
        return craft()->userSession->checkPermission('accessPlugin-commerce');
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return substr($this->number, 0, 7);
    }

    /**
     * @inheritdoc
     * @return string
     */
    public function getLink()
    {
        return TemplateHelper::getRaw("<a href='" . $this->getCpEditUrl() . "'>" . substr($this->number, 0, 7) . "</a>");
    }

    /**
     * @inheritdoc
     * @return string
     */
    public function getCpEditUrl()
    {
        return UrlHelper::getCpUrl('commerce/orders/' . $this->id);
    }

    /**
     * Returns the link to the Order's PDF file for download.
     *
     * @param string $option
     *
     * @return string
     */
    public function getPdfUrl($option = '')
    {
        return UrlHelper::getActionUrl('commerce/downloads/pdf?number=' . $this->number . "&option=" . $option);
    }

    /**
     * @return FieldLayoutModel
     */
    public function getFieldLayout()
    {
        return craft()->commerce_orderSettings->getByHandle('order')->getFieldLayout();
    }

    /**
     * @return bool
     */
    public function isLocalized()
    {
        return false;
    }

    /**
     * @return bool
     */
    public function isPaid()
    {
        $currency = Currency::find(craft()->commerce_settings->getSettings()->defaultCurrency);
        $totalPaid = round($this->totalPaid, $currency->getDecimals());
        $totalPrice = round($this->totalPrice, $currency->getDecimals());
        return $totalPaid >= $totalPrice;
    }

    /**
     * Has the order got any items in it?
     *
     * @return bool
     */
    public function isEmpty()
    {
        return $this->getTotalQty() == 0;
    }

    /**
     * Total number of items.
     *
     * @return int
     */
    public function getTotalQty()
    {
        $qty = 0;
        foreach ($this->lineItems as $item) {
            $qty += $item->qty;
        }

        return $qty;
    }

    /**
     * @return int
     */
    public function getTotalTax()
    {
        $tax = 0;
        foreach ($this->lineItems as $item) {
            $tax += $item->tax;
        }

        return $tax;
    }

    /**
     * @return int
     */
    public function getTotalShippingCost()
    {
        $shippingCost = 0;
        foreach ($this->getLineItems() as $item) {
            $shippingCost += $item->shippingCost;
        }

        return $shippingCost + $this->baseShippingCost;
    }

    /**
     * @return int
     */
    public function getTotalWeight()
    {
        $weight = 0;
        foreach ($this->getLineItems() as $item) {
            $weight += $item->qty * $item->weight;
        }

        return $weight;
    }

    /**
     * @return int
     */
    public function getTotalLength()
    {
        $value = 0;
        foreach ($this->getLineItems() as $item) {
            $value += $item->qty * $item->length;
        }

        return $value;
    }

    /**
     * @return int
     */
    public function getTotalWidth()
    {
        $value = 0;
        foreach ($this->getLineItems() as $item) {
            $value += $item->qty * $item->width;
        }

        return $value;
    }

    /**
     * Returns the total sale amount.
     * @return int
     */
    public function getTotalSaleAmount()
    {
        $value = 0;
        foreach ($this->getLineItems() as $item) {
            $value += $item->qty * $item->saleAmount;
        }

        return $value;
    }

    /**
     * @return int
     */
    public function getItemSubtotalWithSale()
    {
        $value = 0;
        foreach ($this->getLineItems() as $item) {
            $value += $item->getSubtotalWithSale();
        }

        return $value;
    }

    /**
     * @return int
     */
    public function getTotalHeight()
    {
        $value = 0;
        foreach ($this->getLineItems() as $item) {
            $value += $item->qty * $item->height;
        }

        return $value;
    }

    /**
     * @return Commerce_LineItemModel[]
     */
    public function getLineItems()
    {
        if(!$this->_lineItems){
            $this->_lineItems = craft()->commerce_lineItems->getAllLineItemsByOrderId($this->id);
        }

        return $this->_lineItems;
    }

    /**
     * @return Commerce_OrderAdjustmentModel[]
     */
    public function getAdjustments()
    {
        if(!$this->_orderAdjustments){
            $this->_orderAdjustments = craft()->commerce_orderAdjustments->getAllOrderAdjustmentsByOrderId($this->id);
        }

        return $this->_orderAdjustments;
    }

    /**
     * @return Commerce_AddressModel
     */
    public function getShippingAddress()
    {
        if (!isset($this->_shippingAddress)) {
            // Get the live linked address if it is still a cart, else cached
            if (!$this->dateOrdered) {
                $this->_shippingAddress = craft()->commerce_addresses->getAddressById($this->shippingAddressId);
            } else {
                $this->_shippingAddress = Commerce_AddressModel::populateModel($this->shippingAddressData);
            }
        }

        return $this->_shippingAddress;
    }

    /**
     * @param Commerce_AddressModel $address
     */
    public function setShippingAddress(Commerce_AddressModel $address)
    {
        $this->shippingAddressData = JsonHelper::encode($address->attributes);
        $this->_shippingAddress = $address;
    }

    /**
     * @return Commerce_AddressModel
     */
    public function getBillingAddress()
    {
        if (!isset($this->_billingAddress)) {
            // Get the live linked address if it is still a cart, else cached
            if (!$this->dateOrdered) {
                $this->_billingAddress = craft()->commerce_addresses->getAddressById($this->billingAddressId);
            } else {
                $this->_billingAddress = Commerce_AddressModel::populateModel($this->billingAddressData);
            }
        }

        return $this->_billingAddress;
    }

    /**
     *
     * @param Commerce_AddressModel $address
     */
    public function setBillingAddress(Commerce_AddressModel $address)
    {
        $this->billingAddressData = JsonHelper::encode($address->attributes);
        $this->_billingAddress = $address;
    }

    /**
     * @return bool|\Commerce\Interfaces\ShippingMethod
     */
    public function getShippingMethod()
    {
        return craft()->commerce_shippingMethods->getByHandle($this->getAttribute('shippingMethod'));
    }

    /**
     * @return string
     */
    public function getShippingMethodHandle()
    {
        return $this->getAttribute('shippingMethod');
    }

    /**
     * @deprecated
     * @return bool
     */
    public function showAddress()
    {
        craft()->deprecator->log('Commerce_OrderModel::showAddress():removed', 'You should no longer use `cart.showAddress` in twig to determine whether to show the address form. Do your own check in twig like this `{% if cart.linItems|length > 0 %}`');

        return count($this->getLineItems()) > 0;
    }

    /**
     * @deprecated
     * @return bool
     */
    public function showPayment()
    {
        craft()->deprecator->log('Commerce_OrderModel::showPayment():removed', 'You should no longer use `cart.showPayment` in twig to determine whether to show the payment form. Do your own check in twig like this `{% if cart.linItems|length > 0 and cart.billingAddressId and cart.shippingAddressId %}`');

        return count($this->getLineItems()) > 0 && $this->billingAddressId && $this->shippingAddressId;
    }

    /**
     * @return array
     */
    protected function defineAttributes()
    {
        return array_merge(parent::defineAttributes(), [
            'id' => AttributeType::Number,
            'number' => AttributeType::String,
            'couponCode' => AttributeType::String,
            'itemTotal' => [
                AttributeType::Number,
                'decimals' => 4,
                'default' => 0
            ],
            'baseDiscount' => [
                AttributeType::Number,
                'decimals' => 4,
                'default' => 0
            ],
            'baseShippingCost' => [
                AttributeType::Number,
                'decimals' => 4,
                'default' => 0
            ],
            'totalPrice' => [
                AttributeType::Number,
                'decimals' => 4,
                'default' => 0
            ],
            'totalPaid' => [
                AttributeType::Number,
                'decimals' => 4,
                'default' => 0
            ],
            'email' => AttributeType::String,
            'dateOrdered' => AttributeType::DateTime,
            'datePaid' => AttributeType::DateTime,
            'currency' => AttributeType::String,
            'lastIp' => AttributeType::String,
            'message' => AttributeType::String,
            'returnUrl' => AttributeType::String,
            'cancelUrl' => AttributeType::String,
            'orderStatusId' => AttributeType::Number,
            'billingAddressId' => AttributeType::Number,
            'shippingAddressId' => AttributeType::Number,
            'shippingMethod' => AttributeType::String,
            'paymentMethodId' => AttributeType::Number,
            'customerId' => AttributeType::Number,
            'shippingAddressData' => AttributeType::Mixed,
            'billingAddressData' => AttributeType::Mixed
        ]);
    }
}
