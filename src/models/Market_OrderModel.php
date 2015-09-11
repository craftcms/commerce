<?php

namespace Craft;

use Market\Traits\Market_ModelRelationsTrait;

/**
 * Class Market_OrderModel
 *
 * @property int                           id
 * @property string                        number
 * @property string                        couponCode
 * @property float                         itemTotal
 * @property float                         totalPrice
 * @property float                         totalPaid
 * @property float                         baseDiscount
 * @property float                         baseShippingCost
 * @property string                        email
 * @property DateTime                      dateOrdered
 * @property DateTime                      datePaid
 * @property string                        lastIp
 * @property string                        message
 * @property string                        returnUrl
 * @property string                        cancelUrl
 *
 * @property int                           typeId
 * @property int                           billingAddressId
 * @property mixed                         billingAddressData
 * @property int                           shippingAddressId
 * @property mixed                         shippingAddressData
 * @property int                           shippingMethodId
 * @property int                           paymentMethodId
 * @property int                           customerId
 * @property int                           orderStatusId
 *
 * @property int                           totalQty
 * @property int                           totalWeight
 * @property int                           totalHeight
 * @property int                           totalLength
 * @property int                           totalWidth
 * @property string                        pdfUrl
 *
 * @property Market_OrderSettingsModel     type
 * @property Market_LineItemModel[]        lineItems
 * @property Market_AddressModel           billingAddress
 * @property Market_CustomerModel          customer
 * @property Market_AddressModel           shippingAddress
 * @property Market_ShippingMethodModel    shippingMethod
 * @property Market_OrderAdjustmentModel[] adjustments
 * @property Market_PaymentMethodModel     paymentMethod
 * @property Market_TransactionModel[]     transactions
 * @property Market_OrderStatusModel       orderStatus
 * @property Market_OrderHistoryModel[]    histories
 *
 * @package Craft
 */
class Market_OrderModel extends BaseElementModel
{
    use Market_ModelRelationsTrait;

    private $_shippingAddress;
    private $_billingAddress;

    protected $elementType = 'Market_Order';

    public function isEditable()
    {
        return true;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return substr($this->number,0,7);
    }

    /**
     * @inheritdoc
     * @return string
     */
    public function getLink()
    {
        return TemplateHelper::getRaw("<a href='".$this->getCpEditUrl()."'>".substr($this->number,0,7)."</a>");
    }

    /**
     * @inheritdoc
     * @return string
     */
    public function getCpEditUrl()
    {
        return UrlHelper::getCpUrl('market/orders/' . $this->id);
    }

    /**
     * Returns the link to the Order's PDF file for download.
     * @param string $option
     * @return string
     */
    public function getPdfUrl($option = '')
    {
        return UrlHelper::getActionUrl('market/download/pdf?number=' . $this->number . "&option=" . $option);
    }

    /**
     * @return FieldLayoutModel
     */
    public function getFieldLayout()
    {
        return craft()->market_orderSettings->getByHandle('order')->getFieldLayout();
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
        return $this->totalPaid >= $this->totalPrice;
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
     * Has the order got any items in it?
     *
     * @return bool
     */
    public function isEmpty()
    {
        return $this->getTotalQty() == 0;
    }


    /**
     * @return int
     */
    public function getTotalWeight()
    {
        $weight = 0;
        foreach ($this->lineItems as $item) {
            $weight += $item->qty * $item->weight;
        }

        return $weight;
    }

    public function getTotalLength()
    {
        $value = 0;
        foreach ($this->lineItems as $item) {
            $value += $item->qty * $item->length;
        }

        return $value;
    }

    public function getTotalWidth()
    {
        $value = 0;
        foreach ($this->lineItems as $item) {
            $value += $item->qty * $item->width;
        }

        return $value;
    }

    public function getTotalHeight()
    {
        $value = 0;
        foreach ($this->lineItems as $item) {
            $value += $item->qty * $item->height;
        }

        return $value;
    }

    /**
     * @return Market_LineItemModel[]
     */
    public function getLineItems()
    {
        return craft()->market_lineItem->getAllByOrderId($this->id);
    }

    /**
     * @return Market_OrderAdjustmentModel[]
     */
    public function getAdjustments()
    {
        return craft()->market_orderAdjustment->getAllByOrderId($this->id);
    }

    /**
     * @return Market_AddressModel
     */
    public function getShippingAddress()
    {
        if(!isset($this->_shippingAddress)){
            // Get the live linked address if it is still a cart, else cached
            if (!$this->dateOrdered) {
                $this->_shippingAddress = craft()->market_address->getAddressById($this->shippingAddressId);
            }else{
                $this->_shippingAddress = Market_AddressModel::populateModel($this->shippingAddressData);
            }

        }

        return $this->_shippingAddress;
    }

    /**
     * @param Market_AddressModel $address
     */
    public function setShippingAddress(Market_AddressModel $address)
    {
        $this->shippingAddressData = JsonHelper::encode($address->attributes);
        $this->_shippingAddress = $address;
    }

    /**
     * @return Market_AddressModel
     */
    public function getBillingAddress()
    {
        if(!isset($this->_billingAddress))
        {
            // Get the live linked address if it is still a cart, else cached
            if (!$this->dateOrdered)
            {
                $this->_billingAddress = craft()->market_address->getAddressById($this->billingAddressId);
            }
            else
            {
                $this->_billingAddress = Market_AddressModel::populateModel($this->billingAddressData);
            }
        }

        return $this->_billingAddress;
    }


    /**
     *
     * @param Market_AddressModel $address
     */
    public function setBillingAddress(Market_AddressModel $address)
    {
        $this->billingAddressData = JsonHelper::encode($address->attributes);
        $this->_billingAddress = $address;
    }


    /**
     * @deprecated
     * @return bool
     */
    public function showAddress()
    {
        craft()->deprecator->log('Market_OrderModel::showAddress():removed', 'You should no longer use `cart.showAddress` in twig to determine whether to show the address form. Do your own check in twig like this `{% if cart.linItems|length > 0 %}`');
        return count($this->lineItems) > 0;
    }

    /**
     * @deprecated
     * @return bool
     */
    public function showPayment()
    {
        craft()->deprecator->log('Market_OrderModel::showPayment():removed', 'You should no longer use `cart.showPayment` in twig to determine whether to show the payment form. Do your own check in twig like this `{% if cart.linItems|length > 0 and cart.billingAddressId and cart.shippingAddressId %}`');
        return count($this->lineItems) > 0 && $this->billingAddressId && $this->shippingAddressId;
    }

    /**
     * @return array
     */
    protected function defineAttributes()
    {
        return array_merge(parent::defineAttributes(), [
            'id'                => AttributeType::Number,
            'number'            => AttributeType::String,
            'couponCode'        => AttributeType::String,
            'itemTotal'         => [
                AttributeType::Number,
                'decimals' => 4,
                'default'  => 0
            ],
            'baseDiscount'      => [
                AttributeType::Number,
                'decimals' => 4,
                'default'  => 0
            ],
            'baseShippingCost'  => [
                AttributeType::Number,
                'decimals' => 4,
                'default'  => 0
            ],
            'totalPrice'        => [
                AttributeType::Number,
                'decimals' => 4,
                'default'  => 0
            ],
            'totalPaid'         => [
                AttributeType::Number,
                'decimals' => 4,
                'default'  => 0
            ],
            'email'             => AttributeType::String,
            'dateOrdered'       => AttributeType::DateTime,
            'datePaid'          => AttributeType::DateTime,
            'currency'          => AttributeType::String,
            'lastIp'            => AttributeType::String,
            'message'           => AttributeType::String,
            'returnUrl'         => AttributeType::String,
            'cancelUrl'         => AttributeType::String,
            'orderStatusId'     => AttributeType::Number,
            'billingAddressId'  => AttributeType::Number,
            'shippingAddressId' => AttributeType::Number,
            'shippingMethodId'  => AttributeType::Number,
            'paymentMethodId'   => AttributeType::Number,
            'customerId'        => AttributeType::Number,
            'typeId'            => AttributeType::Number,

            'shippingAddressData'   => AttributeType::Mixed,
            'billingAddressData'    => AttributeType::Mixed
        ]);
    }
}