<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\records;

use craft\commerce\db\Table;
use craft\db\ActiveRecord;
use craft\elements\User;
use craft\records\Element;
use DateTime;
use yii\db\ActiveQueryInterface;

/**
 * Order or Cart record.
 *
 * @property int|null $billingAddressId
 * @property string $cancelUrl
 * @property string $couponCode
 * @property string $currency
 * @property ActiveQueryInterface $customer
 * @property int $customerId
 * @property DateTime $dateOrdered
 * @property DateTime $datePaid
 * @property DateTime $dateAuthorized
 * @property ActiveQueryInterface $discount
 * @property ActiveQueryInterface $element
 * @property string $email
 * @property ActiveQueryInterface $gateway
 * @property int $gatewayId
 * @property OrderHistory[] $histories
 * @property int $id
 * @property int $storeId
 * @property bool $isCompleted
 * @property float $itemSubtotal
 * @property float $itemTotal
 * @property string $lastIp
 * @property LineItem[] $lineItems
 * @property string $message
 * @property string $number
 * @property string $orderLanguage
 * @property int $orderSiteId
 * @property string $origin
 * @property OrderStatus $orderStatus
 * @property int $orderStatusId
 * @property string $paidStatus
 * @property string $paymentCurrency
 * @property int $paymentSourceId
 * @property bool $registerUserOnOrderComplete
 * @property bool $saveBillingAddressOnOrderComplete
 * @property bool $saveShippingAddressOnOrderComplete
 * @property string $returnUrl
 * @property string $reference
 * @property string $recalculationMode
 * @property int|null $shippingAddressId
 * @property string $shippingMethodHandle
 * @property string $shippingMethodName
 * @property float $total
 * @property float $totalPaid
 * @property float $totalPrice
 * @property float $totalTax
 * @property float $totalTaxIncluded
 * @property float $totalShippingCost
 * @property float $totalDiscount
 * @property float $totalQty
 * @property float $totalWeight
 * @property ActiveQueryInterface $paymentSource
 * @property int $estimatedBillingAddressId
 * @property int $estimatedShippingAddressId
 * @property int $sourceBillingAddressId
 * @property int $sourceShippingAddressId
 * @property bool $makePrimaryShippingAddress
 * @property bool $makePrimaryBillingAddress
 * @property Transaction[] $transactions
 * @property string $orderCompletedEmail
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Order extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::ORDERS;
    }

    public function getLineItems(): ActiveQueryInterface
    {
        return $this->hasMany(LineItem::class, ['orderId' => 'id']);
    }

    public function getTransactions(): ActiveQueryInterface
    {
        return $this->hasMany(Transaction::class, ['orderId' => 'id']);
    }

    public function getHistories(): ActiveQueryInterface
    {
        return $this->hasMany(OrderHistory::class, ['orderId' => 'id']);
    }

    public function getBillingAddress(): ActiveQueryInterface
    {
        return $this->hasOne(Element::class, ['id' => 'billingAddressId']);
    }

    public function getShippingAddress(): ActiveQueryInterface
    {
        return $this->hasOne(Element::class, ['id' => 'shippingAddressId']);
    }

    public function getDiscount(): ActiveQueryInterface
    {
        return $this->hasOne(Discount::class, ['code' => 'couponCode']);
    }

    public function getGateway(): ActiveQueryInterface
    {
        return $this->hasOne(Gateway::class, ['id' => 'gatewayId']);
    }

    public function getPaymentSource(): ActiveQueryInterface
    {
        return $this->hasOne(PaymentSource::class, ['id' => 'paymentSourceId']);
    }

    public function getCustomer(): ActiveQueryInterface
    {
        return $this->hasOne(User::class, ['id' => 'customerId']);
    }

    public function getElement(): ActiveQueryInterface
    {
        return $this->hasOne(Element::class, ['id' => 'id']);
    }

    public function getOrderStatus(): ActiveQueryInterface
    {
        return $this->hasOne(OrderStatus::class, ['id' => 'orderStatusId']);
    }
}
