<?php

namespace craft\commerce\records;

use craft\db\ActiveRecord;
use craft\records\Element;
use yii\db\ActiveQueryInterface;

/**
 * Order or Cart record.
 *
 * @property int                          $id
 * @property string                       $number
 * @property string                       $couponCode
 * @property float                        $itemTotal
 * @property float                        $totalPrice
 * @property float                        $totalPaid
 * @property string                       $email
 * @property bool                         $isCompleted
 * @property \DateTime                    $dateOrdered
 * @property \DateTime                    $datePaid
 * @property string                       $currency
 * @property string                       $paymentCurrency
 * @property string                       $lastIp
 * @property string                       $orderLocale
 * @property string                       $message
 * @property string                       $returnUrl
 * @property string                       $cancelUrl
 *
 * @property int                          $billingAddressId
 * @property int                          $shippingAddressId
 * @property string                       $shippingMethodHandle
 * @property int                          $gatewayId
 * @property int                          $customerId
 * @property int                          $orderStatusId
 *
 * @property LineItem[]                   $lineItems
 * @property Address                      $billingAddress
 * @property Address                      $shippingAddress
 * @property Gateway                      $paymentMethod
 * @property Transaction[]                $transactions
 * @property OrderStatus                  $orderStatus
 * @property \yii\db\ActiveQueryInterface $customer
 * @property \yii\db\ActiveQueryInterface $element
 * @property \yii\db\ActiveQueryInterface $gateway
 * @property \yii\db\ActiveQueryInterface $discount
 * @property OrderHistory[]               $histories
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
    public static function tableName(): string
    {
        return '{{%commerce_orders}}';
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getLineItems(): ActiveQueryInterface
    {
        return $this->hasMany(LineItem::class, ['orderId' => 'id']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getTransactions(): ActiveQueryInterface
    {
        return $this->hasMany(Transaction::class, ['orderId' => 'id']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getHistories(): ActiveQueryInterface
    {
        return $this->hasMany(OrderHistory::class, ['orderId' => 'id']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getBillingAddress(): ActiveQueryInterface
    {
        return $this->hasOne(Address::class, ['id' => 'billingAddressId']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getShippingAddress(): ActiveQueryInterface
    {
        return $this->hasOne(Address::class, ['id' => 'shippingAddressId']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getDiscount(): ActiveQueryInterface
    {
        return $this->hasOne(Discount::class, ['code' => 'couponCode']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getGateway(): ActiveQueryInterface
    {
        return $this->hasOne(Gateway::class, ['id' => 'gatewayId']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getCustomer(): ActiveQueryInterface
    {
        return $this->hasOne(Customer::class, ['id' => 'customerId']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getElement(): ActiveQueryInterface
    {
        return $this->hasOne(Element::class, ['id' => 'id']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getOrderStatus(): ActiveQueryInterface
    {
        return $this->hasOne(OrderStatus::class, ['id' => 'orderStatusId']);
    }
}
