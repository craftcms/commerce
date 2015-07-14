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
 * @property float                         finalPrice
 * @property float                         baseDiscount
 * @property float                         baseShippingRate
 * @property string                        email
 * @property DateTime                      completedAt
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
 *
 * @property Market_OrderTypeModel         type
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
        return $this->number;
    }

    /**
     * @inheritdoc
     * @return string
     */
    public function getCpEditUrl()
    {
        $orderType = $this->type;

        return UrlHelper::getCpUrl('market/orders/' . $orderType->handle . '/' . $this->id);
    }

    /**
     * @return null|FieldLayoutModel
     */
    public function getFieldLayout()
    {
        if ($this->type) {
            return craft()->market_orderType->getById($this->typeId)->getFieldLayout();
        }

        return null;
    }


    public function isLocalized()
    {
        return false;
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


    public function getAdjustments()
    {
        return craft()->market_orderAdjustment->getAllByOrderId($this->id);
    }

    /**
     * @return Market_AddressModel
     */
    public function getShippingAddress()
    {
        // Get the live linked address if it is still a cart, else cached
        if (!$this->completedAt) {
            return craft()->market_address->getById($this->shippingAddressId);
        }else{
            return Market_AddressModel::populateModel($this->shippingAddressData);
        }
    }

    /**
     * @return Market_AddressModel
     */
    public function getBillingAddress()
    {
        // Get the live linked address if it is still a cart, else cached
        if (!$this->completedAt) {
            return craft()->market_address->getById($this->billingAddressId);
        }else{
            return Market_AddressModel::populateModel($this->billingAddressData);
        }

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
            'baseShippingRate'  => [
                AttributeType::Number,
                'decimals' => 4,
                'default'  => 0
            ],
            'finalPrice'        => [
                AttributeType::Number,
                'decimals' => 4,
                'default'  => 0
            ],
            'email'             => AttributeType::String,
            'completedAt'       => AttributeType::DateTime,
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