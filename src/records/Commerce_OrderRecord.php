<?php

namespace Craft;

/**
 * Order or Cart record.
 *
 * @property int $id
 * @property string $number
 * @property string $couponCode
 * @property float $itemTotal
 * @property float $totalPrice
 * @property float $totalPaid
 * @property float $baseDiscount
 * @property float $baseShippingCost
 * @property float $baseTax
 * @property float $baseTaxIncluded
 * @property string $email
 * @property bool $isCompleted
 * @property DateTime $dateOrdered
 * @property DateTime $datePaid
 * @property string $currency
 * @property string $paymentCurrency
 * @property string $lastIp
 * @property string $orderLocale
 * @property string $message
 * @property string $returnUrl
 * @property string $cancelUrl
 *
 * @property int $billingAddressId
 * @property int $shippingAddressId
 * @property string $shippingMethod
 * @property int $paymentMethodId
 * @property int $customerId
 * @property int $orderStatusId
 *
 * @property Commerce_LineItemRecord[] $lineItems
 * @property Commerce_AddressRecord $billingAddress
 * @property Commerce_AddressRecord $shippingAddress
 * @property Commerce_PaymentMethodRecord $paymentMethod
 * @property Commerce_TransactionRecord[] $transactions
 * @property Commerce_OrderStatusRecord $orderStatus
 * @property Commerce_OrderHistoryRecord[] $histories
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class Commerce_OrderRecord extends BaseRecord
{
    /**
     * Returns the name of the associated database table.
     *
     * @return string
     */
    public function getTableName()
    {
        return 'commerce_orders';
    }

    /**
     * @return array
     */
    public function defineRelations()
    {
        return [
            'lineItems' => [
                static::HAS_MANY,
                'Commerce_LineItemRecord',
                'orderId'
            ],
            'billingAddress' => [static::BELONGS_TO, 'Commerce_AddressRecord'],
            'shippingAddress' => [static::BELONGS_TO, 'Commerce_AddressRecord'],
            'discount' => [
                static::HAS_ONE,
                'Commerce_DiscountRecord',
                ['couponCode' => 'code']
            ],
            'paymentMethod' => [
                static::BELONGS_TO,
                'Commerce_PaymentMethodRecord'
            ],
            'customer' => [static::BELONGS_TO, 'Commerce_CustomerRecord'],
            'transactions' => [
                static::HAS_MANY,
                'Commerce_TransactionRecord',
                'orderId'
            ],
            'element' => [
                static::BELONGS_TO,
                'ElementRecord',
                'id',
                'required' => true,
                'onDelete' => static::CASCADE
            ],
            'orderStatus' => [
                static::BELONGS_TO,
                'Commerce_OrderStatusRecord',
                'onDelete' => static::RESTRICT,
                'onUpdate' => self::CASCADE
            ],
            'histories' => [
                static::HAS_MANY,
                'Commerce_OrderHistoryRecord',
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

    /**
     * @return array
     */
    protected function defineAttributes()
    {
        return [
            'number' => [AttributeType::String, 'length' => 32],
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
            'baseTax' => [
                AttributeType::Number,
                'decimals' => 4,
                'default' => 0
            ],
            'baseTaxIncluded' => [
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
            'isCompleted' => AttributeType::Bool,
            'dateOrdered' => AttributeType::DateTime,
            'datePaid' => AttributeType::DateTime,
            'currency' => AttributeType::String,
            'paymentCurrency' => AttributeType::String,
            'lastIp' => AttributeType::String,
            'orderLocale' => AttributeType::Locale,
            'message' => AttributeType::String,
            'returnUrl' => AttributeType::String,
            'cancelUrl' => AttributeType::String,
            'shippingMethod' => AttributeType::String
        ];
    }

}
