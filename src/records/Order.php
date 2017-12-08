<?php

namespace craft\commerce\records;

use craft\db\ActiveRecord;
use craft\records\Element;
use yii\db\ActiveQueryInterface;

/**
 * Order or Cart record.
 *
 * @property int                  $id
 * @property string               $number
 * @property string               $couponCode
 * @property float                $itemTotal
 * @property float                $totalPrice
 * @property float                $totalPaid
 * @property string               $email
 * @property bool                 $isCompleted
 * @property \DateTime            $dateOrdered
 * @property \DateTime            $datePaid
 * @property string               $currency
 * @property string               $paymentCurrency
 * @property string               $lastIp
 * @property string               $orderLocale
 * @property string               $message
 * @property string               $returnUrl
 * @property string               $cancelUrl
 * @property int                  $billingAddressId
 * @property int                  $shippingAddressId
 * @property string               $shippingMethodHandle
 * @property int                  $gatewayId
 * @property int                  $paymentSourceId
 * @property int                  $customerId
 * @property int                  $orderStatusId
 * @property LineItem[]           $lineItems
 * @property Address              $billingAddress
 * @property Address              $shippingAddress
 * @property Gateway              $paymentMethod
 * @property Transaction[]        $transactions
 * @property OrderStatus          $orderStatus
 * @property ActiveQueryInterface $customer
 * @property ActiveQueryInterface $element
 * @property ActiveQueryInterface $gateway
 * @property ActiveQueryInterface $discount
 * @property OrderHistory[]       $histories
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
class Order extends ActiveRecord
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
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
    public function getPaymentSource(): ActiveQueryInterface
    {
        return $this->hasOne(PaymentSource::class, ['id' => 'paymentSourceId']);
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
