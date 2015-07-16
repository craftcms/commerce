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
 * @property float                         paidTotal
 * @property float                         baseDiscount
 * @property float                         baseShippingRate
 * @property string                        email
 * @property DateTime                      completedAt
 * @property DateTime                      paidAt
 * @property string                        lastIp
 * @property string                        message
 * @property string                        returnUrl
 * @property string                        cancelUrl
 *
 * @property int                           typeId
 * @property int                           billingAddressId
 * @property int                           shippingAddressId
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

    public function isPaid()
    {
        return $this->paidTotal >= $this->finalPrice;
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
        return craft()->market_address->getById($this->shippingAddressId);
    }

    /**
     * @return Market_AddressModel
     */
    public function getBillingAddress()
    {
        return craft()->market_address->getById($this->billingAddressId);
    }

    /**
     * @return bool
     */
    public function showAddress()
    {
        return count($this->lineItems) > 0;
    }

    /**
     * @return bool
     */
    public function showPayment()
    {
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
            'paidTotal'         => [
                AttributeType::Number,
                'decimals' => 4,
                'default'  => 0
            ],
            'email'             => AttributeType::String,
            'completedAt'       => AttributeType::DateTime,
            'paidAt'            => AttributeType::DateTime,
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
        ]);
    }
}