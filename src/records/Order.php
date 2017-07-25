<?php

namespace craft\commerce\records;

use craft\db\ActiveRecord;

/**
 * Order or Cart record.
 *
 * @property int            $id
 * @property string         $number
 * @property string         $couponCode
 * @property float          $itemTotal
 * @property float          $totalPrice
 * @property float          $totalPaid
 * @property float          $baseDiscount
 * @property float          $baseShippingCost
 * @property float          $baseTax
 * @property string         $email
 * @property bool           $isCompleted
 * @property \DateTime      $dateOrdered
 * @property \DateTime      $datePaid
 * @property string         $currency
 * @property string         $paymentCurrency
 * @property string         $lastIp
 * @property string         $orderLocale
 * @property string         $message
 * @property string         $returnUrl
 * @property string         $cancelUrl
 *
 * @property int            $billingAddressId
 * @property int            $shippingAddressId
 * @property string         $shippingMethodHandle
 * @property int            $gatewayId
 * @property int            $customerId
 * @property int            $orderStatusId
 *
 * @property LineItem[]     $lineItems
 * @property Address        $billingAddress
 * @property Address        $shippingAddress
 * @property Gateway        $paymentMethod
 * @property Transaction[]  $transactions
 * @property OrderStatus    $orderStatus
 * @property OrderHistory[] $histories
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class Order extends ActiveRecord
{
    /**
     * Returns the name of the associated database table.
     *
     * @return string
     */
    public static function tableName()
    {
        return '{{%commerce_orders}}';
    }

//    /**
//     * @return array
//     */
//    public function defineRelations()
//    {
//        return [
//            'lineItems' => [
//                static::HAS_MANY,
//                'LineItem',
//                'orderId'
//            ],
//            'billingAddress' => [static::BELONGS_TO, 'Address'],
//            'shippingAddress' => [static::BELONGS_TO, 'Address'],
//            'discount' => [
//                static::HAS_ONE,
//                'Discount',
//                ['couponCode' => 'code']
//            ],
//            'paymentMethod' => [
//                static::BELONGS_TO,
//                'Gateway'
//            ],
//            'customer' => [static::BELONGS_TO, 'Customer'],
//            'transactions' => [
//                static::HAS_MANY,
//                'Transaction',
//                'orderId'
//            ],
//            'element' => [
//                static::BELONGS_TO,
//                'Element',
//                'id',
//                'required' => true,
//                'onDelete' => static::CASCADE
//            ],
//            'orderStatus' => [
//                static::BELONGS_TO,
//                'OrderStatus',
//                'onDelete' => static::RESTRICT,
//                'onUpdate' => self::CASCADE
//            ],
//            'histories' => [
//                static::HAS_MANY,
//                'OrderHistory',
//                'orderId',
//                'order' => 'dateCreated DESC'
//            ],
//        ];
//    }
//
//    /**
//     * @return array
//     */
//    public function defineIndexes()
//    {
//        return [
//            ['columns' => ['number']],
//        ];
//    }
//
//    /**
//     * @return array
//     */
//    protected function defineAttributes()
//    {
//        return [
//            'number' => [AttributeType::String, 'length' => 32],
//            'couponCode' => AttributeType::String,
//            'itemTotal' => [
//                AttributeType::Number,
//                'decimals' => 4,
//                'default' => 0
//            ],
//            'baseDiscount' => [
//                AttributeType::Number,
//                'decimals' => 4,
//                'default' => 0
//            ],
//            'baseShippingCost' => [
//                AttributeType::Number,
//                'decimals' => 4,
//                'default' => 0
//            ],
//            'baseTax' => [
//                AttributeType::Number,
//                'decimals' => 4,
//                'default' => 0
//            ],
//            'totalPrice' => [
//                AttributeType::Number,
//                'decimals' => 4,
//                'default' => 0
//            ],
//            'totalPaid' => [
//                AttributeType::Number,
//                'decimals' => 4,
//                'default' => 0
//            ],
//            'email' => AttributeType::String,
//            'isCompleted' => AttributeType::Bool,
//            'dateOrdered' => AttributeType::DateTime,
//            'datePaid' => AttributeType::DateTime,
//            'currency' => AttributeType::String,
//            'paymentCurrency' => AttributeType::String,
//            'lastIp' => AttributeType::String,
//            'orderLocale' => AttributeType::Locale,
//            'message' => AttributeType::String,
//            'returnUrl' => AttributeType::String,
//            'cancelUrl' => AttributeType::String,
//            'shippingMethod' => AttributeType::String
//        ];
//    }

}
