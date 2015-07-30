<?php

namespace Craft;

/**
 * Class Market_OrderRecord
 *
 * @package Craft
 *
 * @property int                         id
 * @property string                      number
 * @property string                      couponCode
 * @property float                       itemTotal
 * @property float                       totalPrice
 * @property float                       totalPaid
 * @property float                       baseDiscount
 * @property float                       baseShippingCost
 * @property string                      email
 * @property DateTime                    completedAt
 * @property DateTime                    paidAt
 * @property string                      lastIp
 * @property string                      message
 * @property string                      returnUrl
 * @property string                      cancelUrl
 *
 * @property int                         billingAddressId
 * @property mixed                       billingAddressData
 * @property int                         shippingAddressId
 * @property mixed                       shippingAddressData
 * @property int                         shippingMethodId
 * @property int                         paymentMethodId
 * @property int                         customerId
 * @property int                         orderStatusId
 *
 * @property Market_LineItemRecord[]     lineItems
 * @property Market_AddressRecord        billingAddress
 * @property Market_AddressRecord        shippingAddress
 * @property Market_ShippingMethodRecord shippingMethod
 * @property Market_PaymentMethodRecord  paymentMethod
 * @property Market_TransactionRecord[]  transactions
 * @property Market_OrderStatusRecord    orderStatus
 * @property Market_OrderHistoryRecord[] histories
 */
class Market_OrderRecord extends BaseRecord
{
    /**
     * Returns the name of the associated database table.
     *
     * @return string
     */
    public function getTableName()
    {
        return 'market_orders';
    }

    public function defineRelations()
    {
        return [
            'lineItems'       => [
                static::HAS_MANY,
                'Market_LineItemRecord',
                'orderId'
            ],
            'billingAddress'  => [static::BELONGS_TO, 'Market_AddressRecord'],
            'shippingAddress' => [static::BELONGS_TO, 'Market_AddressRecord'],
            'discount'        => [
                static::HAS_ONE,
                'Market_DiscountRecord',
                ['couponCode' => 'code']
            ],
            'shippingMethod'  => [
                static::BELONGS_TO,
                'Market_ShippingMethodRecord'
            ],
            'paymentMethod'   => [
                static::BELONGS_TO,
                'Market_PaymentMethodRecord'
            ],
            'customer'        => [static::BELONGS_TO, 'Market_CustomerRecord'],
            'transactions'    => [
                static::HAS_MANY,
                'Market_TransactionRecord',
                'orderId'
            ],
            'element'         => [
                static::BELONGS_TO,
                'ElementRecord',
                'id',
                'required' => true,
                'onDelete' => static::CASCADE
            ],
            'orderStatus'     => [
                static::BELONGS_TO,
                'Market_OrderStatusRecord',
                'onDelete' => static::RESTRICT,
                'onUpdate' => self::CASCADE
            ],
            'histories'       => [
                static::HAS_MANY,
                'Market_OrderHistoryRecord',
                'orderId',
                'order' => 'dateCreated DESC'
            ],
        ];
    }

    /**
     * @return array
     */
    public function defineIndexes()
    {
        return [
            ['columns' => ['number']],
        ];
    }

    protected function defineAttributes()
    {
        return [
            'number'           => [AttributeType::String, 'length' => 32],
            'couponCode'       => AttributeType::String,
            'itemTotal'        => [
                AttributeType::Number,
                'decimals' => 4,
                'default'  => 0
            ],
            'baseDiscount'     => [
                AttributeType::Number,
                'decimals' => 4,
                'default'  => 0
            ],
            'baseShippingCost' => [
                AttributeType::Number,
                'decimals' => 4,
                'default'  => 0
            ],
            'totalPrice'       => [
                AttributeType::Number,
                'decimals' => 4,
                'default'  => 0
            ],
            'totalPaid'        => [
                AttributeType::Number,
                'decimals' => 4,
                'default'  => 0
            ],
            'email'            => AttributeType::String,
            'completedAt'      => AttributeType::DateTime,
            'paidAt'           => AttributeType::DateTime,
            'currency'         => AttributeType::String,
            'lastIp'           => AttributeType::String,
            'message'          => AttributeType::String,
            'returnUrl'        => AttributeType::String,
            'cancelUrl'        => AttributeType::String,

            'billingAddressData'  => AttributeType::Mixed,
            'shippingAddressData'  => AttributeType::Mixed
        ];
    }

}
