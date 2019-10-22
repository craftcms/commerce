<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\records;

use craft\commerce\db\Table;
use craft\db\ActiveRecord;
use craft\records\Element;
use DateTime;
use yii\db\ActiveQueryInterface;

/**
 * Order or Cart record.
 *
 * @property Address $billingAddress
 * @property int $billingAddressId
 * @property string $cancelUrl
 * @property string $couponCode
 * @property string $currency
 * @property ActiveQueryInterface $customer
 * @property int $customerId
 * @property DateTime $dateOrdered
 * @property DateTime $datePaid
 * @property ActiveQueryInterface $discount
 * @property ActiveQueryInterface $element
 * @property string $email
 * @property ActiveQueryInterface $gateway
 * @property int $gatewayId
 * @property OrderHistory[] $histories
 * @property int $id
 * @property bool $isCompleted
 * @property float $itemTotal
 * @property string $lastIp
 * @property LineItem[] $lineItems
 * @property string $message
 * @property string $number
 * @property string $orderLanguage
 * @property OrderStatus $orderStatus
 * @property int $orderStatusId
 * @property string $paidStatus
 * @property string $paymentCurrency
 * @property int $paymentSourceId
 * @property string $registerUserOnOrderComplete
 * @property string $returnUrl
 * @property string $reference
 * @property Address $shippingAddress
 * @property int $shippingAddressId
 * @property string $shippingMethodHandle
 * @property float $total
 * @property float $totalPaid
 * @property float $totalPrice
 * @property int $estimatedBillingAddressId
 * @property int $estimatedShippingAddressId
 * @property Transaction[] $transactions
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
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
        return Table::ORDERS;
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
