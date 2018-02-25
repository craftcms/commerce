<?php

namespace craft\commerce\elements;

use Craft;
use craft\commerce\base\Element;
use craft\commerce\base\Gateway;
use craft\commerce\base\GatewayInterface;
use craft\commerce\base\ShippingMethodInterface;
use craft\commerce\elements\actions\UpdateOrderStatus;
use craft\commerce\elements\db\OrderQuery;
use craft\commerce\events\OrderEvent;
use craft\commerce\helpers\Currency;
use craft\commerce\models\Address;
use craft\commerce\models\Customer;
use craft\commerce\models\LineItem;
use craft\commerce\models\OrderAdjustment;
use craft\commerce\models\OrderHistory;
use craft\commerce\models\OrderSettings;
use craft\commerce\models\OrderStatus;
use craft\commerce\models\PaymentSource;
use craft\commerce\models\ShippingMethod;
use craft\commerce\models\Transaction;
use craft\commerce\Plugin;
use craft\commerce\records\LineItem as LineItemRecord;
use craft\commerce\records\Order as OrderRecord;
use craft\commerce\records\OrderAdjustment as OrderAdjustmentRecord;
use craft\elements\actions\Delete;
use craft\elements\db\ElementQueryInterface;
use craft\elements\User;
use craft\errors\DefaultOrderStatusNotFoundException;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use craft\helpers\Template;
use craft\helpers\UrlHelper;
use yii\base\Exception;

/**
 * Order or Cart model.
 *
 * @property OrderAdjustment[] $adjustments
 * @property float|int $adjustmentSubtotal the total of adjustments made to order
 * @property float $adjustmentsTotal
 * @property Address $billingAddress
 * @property Customer $customer
 * @property Gateway $gateway
 * @property OrderHistory[] $histories order histories
 * @property int $itemSubtotal the total of all line item subtotals
 * @property float $itemTotal
 * @property LineItem[] $lineItems
 * @property array|Transaction[] $nestedTransactions transactions for the order that have child transactions set on them
 * @property array $orderAdjustments
 * @property OrderStatus $orderStatus
 * @property string $pdfUrl the URL to the order’s PDF invoice
 * @property Address $shippingAddress
 * @property ShippingMethodInterface $shippingMethod
 * @property ShippingMethodInterface $shippingMethodId
 * @property string $shortNumber
 * @property bool $shouldRecalculateAdjustments
 * @property int $totalDiscount
 * @property int $totalHeight
 * @property int $totalLength
 * @property float $totalPaid the total `purchase` and `captured` transactions belonging to this order
 * @property float $totalPrice
 * @property int $totalQty the total number of items
 * @property int $totalSaleAmount the total sale amount
 * @property int $totalShippingCost
 * @property int $totalTax
 * @property float $totalTaxablePrice
 * @property float $totalTaxIncluded
 * @property int $totalWeight
 * @property Transaction[] $transactions
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Order extends Element
{
    // Constants
    // =========================================================================

    /**
     * @event OrderEvent This event is raised when an order is completed
     *
     * Plugins can get notified before an order is completed
     *
     * ```php
     * use craft\commerce\events\OrderEvent;
     * use craft\commerce\services\Order;
     * use yii\base\Event;
     *
     * Event::on(Order::class, Order::EVENT_BEFORE_COMPLETE_ORDER, function(OrderEvent $e) {
     *     // Do something - perhaps let the accounting system know about the order.
     * });
     * ```
     */
    const EVENT_BEFORE_COMPLETE_ORDER = 'beforeCompleteOrder';

    /**
     * @event OrderEvent This event is raised after an order is completed
     *
     * Plugins can get notified before an address is being saved
     *
     * ```php
     * use craft\commerce\events\OrderEvent;
     * use craft\commerce\services\Order;
     * use yii\base\Event;
     *
     * Event::on(Order::class, Order::EVENT_AFTER_COMPLETE_ORDER, function(OrderEvent $e) {
     *     // Do something - maybe signal the custom warehouse solution to reserve stock.
     * });
     * ```
     */
    const EVENT_AFTER_COMPLETE_ORDER = 'afterCompleteOrder';

    // Properties
    // =========================================================================

    /**
     * @inheritdoc
     */
    public $id;

    /**
     * @var string Number
     */
    public $number;

    /**
     * @var string Coupon Code
     */
    public $couponCode;

    /**
     * @var string Email
     */
    public $email = 0;

    /**
     * @var bool Is completed
     */
    public $isCompleted = 0;

    /**
     * @var \DateTime Date ordered
     */
    public $dateOrdered;

    /**
     * @var \DateTime Date paid
     */
    public $datePaid;

    /**
     * @var string Currency
     */
    public $currency;

    /**
     * @var string Payment Currency
     */
    public $paymentCurrency;

    /**
     * @var int|null Gateway ID
     */
    public $gatewayId;

    /**
     * @var int|null Payment source ID
     */
    public $paymentSourceId;

    /**
     * @var string Last IP
     */
    public $lastIp;

    /**
     * @var string Order locale
     */
    public $orderLocale;

    /**
     * @var string Message
     */
    public $message;

    /**
     * @var string Return URL
     */
    public $returnUrl;

    /**
     * @var string Cancel URL
     */
    public $cancelUrl;

    /**
     * @var int Order status ID
     */
    public $orderStatusId;

    /**
     * @var int Billing address ID
     */
    public $billingAddressId;

    /**
     * @var int Shipping address ID
     */
    public $shippingAddressId;

    /**
     * @var string Shipping Method Handle
     */
    public $shippingMethodHandle;

    /**
     * @var int Customer ID
     */
    public $customerId;

    /**
     * @var Address
     */
    private $_shippingAddress;

    /**
     * @var Address
     */
    private $_billingAddress;

    /**
     * @var LineItem[]
     */
    private $_lineItems;

    /**
     * @var OrderAdjustment[]
     */
    private $_orderAdjustments;

    /**
     * @var string Email
     */
    private $_email;

    /**
     * @var bool Should the order recalculate?
     */
    private $_relcalculate = true;

    // Public Methods
    // =========================================================================

    /**
     * @return null|string
     */
    public static function displayName(): string
    {
        return Craft::t('commerce', 'Orders');
    }

    /**
     * @inheritdoc
     * @return OrderQuery The newly created [[OrderQuery]] instance.
     */
    public static function find(): ElementQueryInterface
    {
        return new OrderQuery(static::class);
    }

    /**
     * @inheritdoc
     */
    public static function hasContent(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function __toString()
    {
        return $this->getShortNumber();
    }

    /**
     * @inheritdoc
     */
    public function beforeValidate(): bool
    {
        // Set default gateway if none present and no payment source selected
        if (!$this->gatewayId && !$this->paymentSourceId) {
            $gateways = Plugin::getInstance()->getGateways()->getAllFrontEndGateways();
            if (count($gateways)) {
                $this->gatewayId = key($gateways);
            }
        }

        // Get the customer ID from the session
        if (!$this->customerId && !Craft::$app->request->isConsoleRequest) {
            $this->customerId = Plugin::getInstance()->getCustomers()->getCustomerId();
        }

        $this->email = Plugin::getInstance()->getCustomers()->getCustomerById($this->customerId)->email;

        return parent::beforeValidate();
    }

    /**
     * @inheritdoc
     */
    public function datetimeAttributes(): array
    {
        $attributes = parent::datetimeAttributes();
        $attributes[] = 'datePaid';
        $attributes[] = 'dateOrdered';
        return $attributes;
    }

    /**
     * @inheritdoc
     */
    public function attributes()
    {
        $names = parent::attributes();
        $names[] = 'lineItems';
        $names[] = 'adjustments';
        return $names;
    }

    /**
     * Updates the paid amounts on the order, and marks as complete if the order is paid.
     */
    public function updateOrderPaidTotal()
    {
        if ($this->isPaid()) {
            if ($this->datePaid === null) {
                $this->datePaid = Db::prepareDateForDb(new \DateTime());
            }
        }

        // Lock for recalculation
        $originalShouldRecalculate = $this->getShouldRecalculateAdjustments();
        $this->setShouldRecalculateAdjustments(false);
        Craft::$app->getElements()->saveElement($this);

        if (!$this->isCompleted) {
            if ($this->isPaid()) {
                $this->markAsComplete();
            } else {
                // maybe not paid in full, but authorized enough to complete order.
                $totalAuthorized = Plugin::getInstance()->getPayments()->getTotalAuthorizedForOrder($this);
                if ($totalAuthorized >= $this->getTotalPrice()) {
                    $this->markAsComplete();
                }
            }
        }

        // restore recalculation lock state
        $this->setShouldRecalculateAdjustments($originalShouldRecalculate);
    }

    /**
     * @return float
     */
    public function getTotalTaxablePrice(): float
    {
        $itemTotal = $this->getItemSubtotal();

        $allNonIncludedAdjustmentsTotal = $this->getAdjustmentsTotal();
        $taxAdjustments = $this->getAdjustmentsTotalByType('tax', true);

        return $itemTotal + $allNonIncludedAdjustmentsTotal - $taxAdjustments;
    }

    /**
     * @return bool
     */
    public function getShouldRecalculateAdjustments(): bool
    {
        return $this->_relcalculate;
    }

    /**
     * @param bool $value
     */
    public function setShouldRecalculateAdjustments(bool $value)
    {
        $this->_relcalculate = $value;
    }

    /**
     * @return bool
     * @throws DefaultOrderStatusNotFoundException
     * @throws Exception
     * @throws \Throwable
     * @throws \craft\errors\ElementNotFoundException
     */
    public function markAsComplete(): bool
    {
        if ($this->isCompleted) {
            return true;
        }

        $this->isCompleted = true;
        $this->dateOrdered = Db::prepareDateForDb(new \DateTime());
        $orderStatus = Plugin::getInstance()->getOrderStatuses()->getDefaultOrderStatusForOrder($this);

        // If the order status returned was overridden by a plugin, use the configured default order status if they give us a bogus one with no ID.
        if ($orderStatus && $orderStatus->id) {
            $this->orderStatusId = $orderStatus->id;
        } else {
            throw new DefaultOrderStatusNotFoundException('Could not find a valid default order status.');
        }

        // Raising the 'beforeCompleteOrder' event
        if ($this->hasEventHandlers(self::EVENT_BEFORE_COMPLETE_ORDER)) {
            $this->trigger(self::EVENT_BEFORE_COMPLETE_ORDER, new OrderEvent(['order' => $this]));
        }

        if (Craft::$app->getElements()->saveElement($this)) {
            // Run order complete handlers directly.
            Plugin::getInstance()->getDiscounts()->orderCompleteHandler($this);
            Plugin::getInstance()->getVariants()->orderCompleteHandler($this);
            Plugin::getInstance()->getCustomers()->orderCompleteHandler($this);

            // Raising the 'afterCompleteOrder' event
            if ($this->hasEventHandlers(self::EVENT_AFTER_COMPLETE_ORDER)) {
                $this->trigger(self::EVENT_AFTER_COMPLETE_ORDER, new OrderEvent(['order' => $this]));
            }

            return true;
        }

        Craft::error(Craft::t('commerce', 'Could not mark order {number} as complete. Order save failed during order completion with errors: {order}',
            ['number' => $this->number, 'order' => json_encode($this->errors)]), __METHOD__);

        return false;
    }

    /**
     * Removes a specific line item from the order.
     *
     * @param $lineItem
     */
    public function removeLineItem($lineItem)
    {
        $lineItems = $this->getLineItems();
        foreach ($lineItems as $key => $item) {
            if ($lineItem->id == $item->id) {
                unset($lineItems[$key]);
                $this->setLineItems($lineItems);
            }
        }
    }

    /**
     * Adds a line item to the order.
     *
     * @param $lineItem
     */
    public function addLineItem($lineItem)
    {
        $lineItems = $this->getLineItems();
        $this->setLineItems(array_merge($lineItems, [$lineItem]));
    }

    /**
     * Regenerates all adjusters and update line item and order totals.
     *
     * @throws Exception
     */
    public function recalculate()
    {
        // Don't recalculate the totals of completed orders.
        if (!$this->id || $this->isCompleted || !$this->getShouldRecalculateAdjustments()) {
            return;
        }

        //clear adjustments
        $this->setAdjustments([]);

        $lineItemRemoved = false;
        foreach ($this->getLineItems() as $item) {
            if (!$item->refreshFromPurchasable()) {
                $this->removeLineItem($item);
                $lineItemRemoved = true;
            }
        }

        if ($lineItemRemoved) {
            $this->recalculate();
            return;
        }

        // collect new adjustments
        foreach (Plugin::getInstance()->getOrderAdjustments()->getAdjusters() as $adjuster) {
            $adjustments = (new $adjuster)->adjust($this);
            $this->setAdjustments(array_merge($this->getAdjustments(), $adjustments));
        }

        // Since shipping adjusters run on the original price, pre discount, let's recalculate
        // if the currently selected shipping method is now not available after adjustments have run.
        $availableMethods = Plugin::getInstance()->getShippingMethods()->getAvailableShippingMethods($this);
        if ($this->shippingMethodHandle) {
            if (!isset($availableMethods[$this->shippingMethodHandle]) || empty($availableMethods)) {
                $this->shippingMethodHandle = null;
                $this->recalculate();

                return;
            }
        }
    }

    /**
     * @return float
     */
    public function getItemTotal(): float
    {
        $total = 0;

        foreach ($this->getLineItems() as $lineItem) {
            $total += $lineItem->getSubtotal();
        }

        return $total;
    }

    /**
     * @inheritdoc
     */
    public function afterSave(bool $isNew)
    {
        // TODO: Move the recalculate to somewhere else. Saving should be saving only
        // Right now orders always recalc when saved and not completed but that shouldn't be the case.
        $this->recalculate();

        if (!$isNew) {
            $orderRecord = OrderRecord::findOne($this->id);

            if (!$orderRecord) {
                throw new Exception('Invalid order ID: '.$this->id);
            }
        } else {
            $orderRecord = new OrderRecord();
            $orderRecord->id = $this->id;
        }

        $oldStatusId = $orderRecord->orderStatusId;

        $orderRecord->number = $this->number;
        $orderRecord->itemTotal = $this->getItemTotal();
        $orderRecord->email = $this->getEmail();
        $orderRecord->isCompleted = $this->isCompleted;
        $orderRecord->dateOrdered = $this->dateOrdered;
        $orderRecord->datePaid = $this->datePaid ?: null;
        $orderRecord->billingAddressId = $this->billingAddressId;
        $orderRecord->shippingAddressId = $this->shippingAddressId;
        $orderRecord->shippingMethodHandle = $this->shippingMethodHandle;
        $orderRecord->gatewayId = $this->gatewayId;
        $orderRecord->paymentSourceId = $this->paymentSourceId;
        $orderRecord->orderStatusId = $this->orderStatusId;
        $orderRecord->couponCode = $this->couponCode;
        $orderRecord->totalPrice = $this->getTotalPrice();
        $orderRecord->totalPaid = $this->getTotalPaid();
        $orderRecord->currency = $this->currency;
        $orderRecord->lastIp = $this->lastIp;
        $orderRecord->orderLocale = $this->orderLocale;
        $orderRecord->paymentCurrency = $this->paymentCurrency;
        $orderRecord->customerId = $this->customerId;
        $orderRecord->returnUrl = $this->returnUrl;
        $orderRecord->cancelUrl = $this->cancelUrl;
        $orderRecord->message = $this->message;

        $orderRecord->save(false);

        $this->_updateAdjustments();
        $this->_updateLineItems();

        if ($this->isCompleted) {
            //creating order history record
            $hasNewStatus = $orderRecord->id && ($oldStatusId != $orderRecord->orderStatusId);

            if ($hasNewStatus && !Plugin::getInstance()->getOrderHistories()->createOrderHistoryFromOrder($this, $oldStatusId)) {
                Craft::error('Error saving order history after Order save.', __METHOD__);
                throw new Exception('Error saving order history');
            }
        }

        return parent::afterSave($isNew);
    }

    /**
     * @return bool
     */
    public function isEditable(): bool
    {
        // Still a cart, allow full editing.
        if (!$this->isCompleted) {
            return true;
        }

        return Craft::$app->getUser()->checkPermission('commerce-manageOrders');
    }

    /**
     * @return string
     */
    public function getShortNumber(): string
    {
        return substr($this->number, 0, 7);
    }

    /**
     * @inheritdoc
     */
    public function getLink(): string
    {
        return Template::raw("<a href='".$this->getCpEditUrl()."'>".substr($this->number, 0, 7).'</a>');
    }

    /**
     * @inheritdoc
     */
    public function getCpEditUrl(): string
    {
        return UrlHelper::cpUrl('commerce/orders/'.$this->id);
    }

    /**
     * Returns the URL to the order’s PDF invoice.
     *
     * @param string|null $option The option that should be available to the PDF template (e.g. “receipt”)
     * @return string|null The URL to the order’s PDF invoice, or null if the PDF template doesn’t exist
     */
    public function getPdfUrl($option = null)
    {
        $url = null;

        try {
            $pdf = Plugin::getInstance()->getPdf()->pdfForOrder($this, $option);
            if ($pdf) {
                $url = UrlHelper::actionUrl("commerce/downloads/pdf?number={$this->number}".($option ? "&option={$option}" : null));
            }
        } catch (\Exception $exception) {
            Craft::error($exception->getMessage());
            return null;
        }

        return $url;
    }

    /**
     * @inheritdoc
     */
    public function getFieldLayout()
    {
        /** @var OrderSettings $orderSettings */
        $orderSettings = Plugin::getInstance()->getOrderSettings()->getOrderSettingByHandle('order');

        if ($orderSettings) {
            return $orderSettings->getFieldLayout();
        }

        return null;
    }

    /**
     * Returns whether or not this order is made by a guest user.
     *
     * @return bool
     */
    public function isGuest(): bool
    {
        if ($this->getCustomer()) {
            return !$this->getCustomer()->userId;
        }

        return true;
    }

    /**
     * @return Customer|null
     */
    public function getCustomer()
    {
        if ($this->customerId) {
            return Plugin::getInstance()->getCustomers()->getCustomerById($this->customerId);
        }

        return null;
    }

    /**
     * @return User|null
     */
    public function getUser()
    {
        return $this->getCustomer() ? $this->getCustomer()->getUser() : null;
    }

    /**
     * Returns the email for this order. Will always be the registered users email if the order's customer is related to a user.
     *
     * @return string
     */
    public function getEmail(): string
    {
        if ($this->getCustomer() && $this->getCustomer()->getUser()) {
            $this->setEmail($this->getCustomer()->getUser()->email);
        }

        return $this->_email ?? '';
    }

    /**
     * Sets the orders email address. Will have no affect if the order's customer is a registered user.
     *
     * @param $value
     */
    public function setEmail($value)
    {
        $this->_email = $value;
    }

    /**
     * @return bool
     */
    public function isPaid(): bool
    {
        return $this->outstandingBalance() <= 0;
    }

    /**
     * @return float
     */
    public function getTotalPrice(): float
    {
        return Currency::round($this->getItemTotal() + $this->getAdjustmentsTotal());
    }

    /**
     * Returns the difference between the order amount and amount paid.
     *
     * @return float
     */
    public function outstandingBalance(): float
    {
        $totalPaid = Currency::round($this->totalPaid);
        $totalPrice = Currency::round($this->totalPrice);

        return $totalPrice - $totalPaid;
    }

    /**
     * Returns the total `purchase` and `captured` transactions belonging to this order.
     *
     * @return float
     */
    public function getTotalPaid(): float
    {
        return Plugin::getInstance()->getPayments()->getTotalPaidForOrder($this);
    }

    /**
     * @return bool
     */
    public function isUnpaid(): bool
    {
        return $this->outstandingBalance() > 0;
    }

    /**
     * Returns whether this order is the user's current active cart.
     *
     * @return bool
     */
    public function isActiveCart(): bool
    {
        $cart = Plugin::getInstance()->getCart()->getCart();

        return ($cart && $cart->id == $this->id);
    }

    /**
     * Returns whether the order has any items in it.
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->getTotalQty() == 0;
    }

    /**
     * Returns total number of items.
     *
     * @return int
     */
    public function getTotalQty(): int
    {
        $qty = 0;
        foreach ($this->getLineItems() as $item) {
            $qty += $item->qty;
        }

        return $qty;
    }

    /**
     * @return LineItem[]
     */
    public function getLineItems(): array
    {
        if (null === $this->_lineItems) {
            $this->setLineItems($this->id ? Plugin::getInstance()->getLineItems()->getAllLineItemsByOrderId($this->id) : []);
        }

        return $this->_lineItems;
    }

    /**
     * @param LineItem[] $lineItems
     */
    public function setLineItems(array $lineItems)
    {
        $this->_lineItems = $lineItems;
    }

    /**
     * @param string|array $type
     * @param bool $includedOnly
     * @return float|int
     */
    public function getAdjustmentsTotalByType($type, $includedOnly = false)
    {
        $amount = 0;

        if (is_string($type)) {
            $type = StringHelper::split($type);
        }

        foreach ($this->getAdjustments() as $adjustment) {
            if ($adjustment->included == $includedOnly && in_array($adjustment->type, $type)) {
                $amount += $adjustment->amount;
            }
        }

        return $amount;
    }

    /**
     * @deprecated
     * @return float
     */
    public function getTotalTax(): float
    {
        Craft::$app->getDeprecator()->log('Order::getTotalTax()', 'Order::getTotalTax() has been deprecated. Use Order::getAdjustmentsTotalByType("taxIncluded") ');

        return $this->getAdjustmentsTotalByType('tax');
    }

    /**
     * @deprecated
     * @return float
     */
    public function getTotalTaxIncluded(): float
    {
        Craft::$app->getDeprecator()->log('Order::getTotalTaxIncluded()', 'Order::getTax() has been deprecated. Use Order::getAdjustmentsTotalByType("taxIncluded") ');

        return $this->getAdjustmentsTotalByType('tax', true);
    }

    /**
     * @deprecated
     * @return float
     */
    public function getTotalDiscount(): float
    {
        Craft::$app->getDeprecator()->log('Order::getTotalDiscount()', 'Order::getTotalDiscount() has been deprecated. Use Order::getAdjustmentsTotalByType("discount") ');

        return $this->getAdjustmentsTotalByType('discount');
    }

    /**
     * @deprecated
     * @return float
     */
    public function getTotalShippingCost(): float
    {
        Craft::$app->getDeprecator()->log('Order::getTotalDiscount()', 'Order::getTotalDiscount() has been deprecated. Use Order::getAdjustmentsTotalByType("discount") ');

        return $this->getAdjustmentsTotalByType('discount');
    }

    /**
     * @return float
     */
    public function getTotalWeight(): float
    {
        $weight = 0;
        foreach ($this->getLineItems() as $item) {
            $weight += ($item->qty * $item->weight);
        }

        return $weight;
    }

    /**
     * Returns the total sale amount.
     *
     * @return float
     */
    public function getTotalSaleAmount(): float
    {
        $value = 0;
        foreach ($this->getLineItems() as $item) {
            $value += ($item->qty * $item->saleAmount);
        }

        return $value;
    }

    /**
     * Returns the total of all line item's subtotals.
     *
     * @return float
     */
    public function getItemSubtotal(): float
    {
        $value = 0;
        foreach ($this->getLineItems() as $item) {
            $value += $item->getSubtotal();
        }

        return $value;
    }

    /**
     * Returns the total of adjustments made to order.
     *
     * @return float|int
     */
    public function getAdjustmentSubtotal(): float
    {
        $value = 0;
        foreach ($this->getAdjustments() as $adjustment) {
            if (!$adjustment->included) {
                $value += $adjustment->amount;
            }
        }

        return $value;
    }

    /**
     * @return OrderAdjustment[]
     */
    public function getAdjustments(): array
    {
        if (null === $this->_orderAdjustments) {
            $this->setAdjustments(Plugin::getInstance()->getOrderAdjustments()->getAllOrderAdjustmentsByOrderId($this->id));
        }

        return $this->_orderAdjustments;
    }

    /**
     * @return array
     */
    public function getOrderAdjustments(): array
    {
        $adjustments = $this->getAdjustments();
        $orderAdjustments = [];

        foreach ($adjustments as $adjustment) {
            if ($adjustment->lineItemId == null && $adjustment->orderId == $this->id) {
                $orderAdjustments[] = $adjustment;
            }
        }

        return $orderAdjustments;
    }

    /**
     * @param OrderAdjustment[] $adjustments
     */
    public function setAdjustments(array $adjustments)
    {
        $this->_orderAdjustments = $adjustments;
    }

    /**
     * @return float
     */
    public function getAdjustmentsTotal(): float
    {
        $amount = 0;

        foreach ($this->getAdjustments() as $adjustment) {
            if (!$adjustment->included) {
                $amount += $adjustment->amount;
            }
        }

        return $amount;
    }

    /**
     * @return Address|null
     */
    public function getShippingAddress()
    {
        if (null === $this->_shippingAddress) {
            $this->_shippingAddress = $this->shippingAddressId ? Plugin::getInstance()->getAddresses()->getAddressById($this->shippingAddressId) : null;
        }

        return $this->_shippingAddress;
    }

    /**
     * @param Address $address
     */
    public function setShippingAddress(Address $address)
    {
        $this->_shippingAddress = $address;
    }

    /**
     * @return Address|null
     */
    public function getBillingAddress()
    {
        if (null === $this->_billingAddress) {
            $this->_billingAddress = $this->billingAddressId ? Plugin::getInstance()->getAddresses()->getAddressById($this->billingAddressId) : null;
        }

        return $this->_billingAddress;
    }

    /**
     * @param Address $address
     */
    public function setBillingAddress(Address $address)
    {
        $this->_billingAddress = $address;
    }

    /**
     * @return int|null
     */
    public function getShippingMethodId()
    {
        if ($this->getShippingMethod()) {
            return $this->getShippingMethod()->getId();
        }

        return null;
    }

    /**
     * @return ShippingMethod|null
     */
    public function getShippingMethod()
    {
        return Plugin::getInstance()->getShippingMethods()->getShippingMethodByHandle((string)$this->shippingMethodHandle);
    }

    /**
     * @return GatewayInterface|null
     */
    public function getGateway()
    {
        if ($this->paymentSourceId) {
            $paymentSource = Plugin::getInstance()->getPaymentSources()->getPaymentSourceById($this->paymentSourceId);

            if ($paymentSource) {
                return Plugin::getInstance()->getGateways()->getGatewayById($paymentSource->gatewayId);
            }
        }

        if ($this->gatewayId) {
            return Plugin::getInstance()->getGateways()->getGatewayById($this->gatewayId);
        }

        return null;
    }

    /**
     * Returns the order's selected payment source if any.
     *
     * @return PaymentSource
     */
    public function getPaymentSource()
    {
        if ($this->paymentSourceId) {
            return Plugin::getInstance()->getPaymentSources()->getPaymentSourceById($this->paymentSourceId);
        }

        return null;
    }

    /**
     * @return OrderHistory[]
     */
    public function getHistories(): array
    {
        $histories = Plugin::getInstance()->getOrderHistories()->getAllOrderHistoriesByOrderId($this->id);

        return $histories;
    }

    /**
     * @return Transaction[]
     */
    public function getTransactions(): array
    {
        return Plugin::getInstance()->getTransactions()->getAllTransactionsByOrderId($this->id);
    }

    /**
     * Returns an array of transactions for the order that have child transactions set on them.
     *
     * @return Transaction[]
     */
    public function getNestedTransactions(): array
    {
        // Transactions come in sorted by `id ASC`.
        // Given that transactions cannot be modified, it means that parents will always come first.
        // So we can just store a reference to them and build our tree in one pass.
        $transactions = $this->getTransactions();

        /** @var Transaction[] $referenceStore */
        $referenceStore = [];
        $nestedTransactions = [];

        foreach ($transactions as $transaction) {
            // We'll be adding all of the children in this loop, anyway, so we set the children list to an empty array.
            // This way no db queries are triggered when transactions are queried for children.
            $transaction->setChildTransactions([]);
            if ($transaction->parentId && isset($referenceStore[$transaction->parentId])) {
                $referenceStore[$transaction->parentId]->addChildTransaction($transaction);
            } else {
                $nestedTransactions[] = $transaction;
            }

            $referenceStore[$transaction->id] = $transaction;
        }

        return $nestedTransactions;
    }

    /**
     * @return OrderStatus|null
     */
    public function getOrderStatus()
    {
        return Plugin::getInstance()->getOrderStatuses()->getOrderStatusById($this->orderStatusId);
    }

    /**
     * @inheritdoc
     */
    public function getTableAttributeHtml(string $attribute): string
    {
        switch ($attribute) {
            case 'orderStatus':
                {
                    if ($this->orderStatus) {
                        return $this->orderStatus->htmlLabel();
                    }

                    return '<span class="status"></span>';
                }
            case 'shippingFullName':
                {
                    if ($this->shippingAddress) {
                        return $this->shippingAddress->getFullName();
                    }

                    return '';
                }
            case 'billingFullName':
                {
                    if ($this->billingAddress) {
                        return $this->billingAddress->getFullName();
                    }

                    return '';
                }
            case 'shippingBusinessName':
                {
                    if ($this->shippingAddress) {
                        return $this->shippingAddress->businessName;
                    }

                    return '';
                }
            case 'billingBusinessName':
                {
                    if ($this->billingAddress) {
                        return $this->billingAddress->businessName;
                    }

                    return '';
                }
            case 'shippingMethodName':
                {
                    if ($this->shippingMethod) {
                        return $this->shippingMethod->getName();
                    }

                    return '';
                }
            case 'gatewayName':
                {
                    if ($this->gateway) {
                        return $this->gateway->name;
                    }

                    return '';
                }
            case 'totalPaid':
            case 'totalPrice':
            case 'totalShippingCost':
            case 'totalDiscount':
                {
                    if ($this->$attribute >= 0) {
                        return Craft::$app->getFormatter()->asCurrency($this->$attribute, $this->currency);
                    }

                    return Craft::$app->getFormatter()->asCurrency($this->$attribute * -1, $this->currency);
                }
            default:
                {
                    return parent::tableAttributeHtml($attribute);
                }
        }
    }

    /**
     * @inheritdoc
     */
    protected static function defineSearchableAttributes(): array
    {
        return [
            'number',
            'email',
            'shortNumber',
            'billingFirstName',
            'billingLastName',
            'shippingFirstName',
            'shippingLastName',
            'transactionReference'
        ];
    }

    /**
     * @inheritdoc
     */
    public function getSearchKeywords(string $attribute): string
    {
        if ($attribute === 'shortNumber') {
            return $this->getShortNumber();
        }

        if ($attribute === 'email') {
            return $this->getEmail();
        }

        if ($attribute === 'billingFirstName') {
            if ($this->getBillingAddress() && $this->getBillingAddress()->firstName) {
                return $this->billingAddress->firstName;
            }

            return '';
        }

        if ($attribute === 'billingLastName') {
            if ($this->getBillingAddress() && $this->getBillingAddress()->lastName) {
                return $this->billingAddress->lastName;
            }

            return '';
        }

        if ($attribute === 'shippingFirstName') {
            if ($this->getShippingAddress() && $this->getShippingAddress()->firstName) {
                return $this->shippingAddress->firstName;
            }

            return '';
        }

        if ($attribute === 'shippingLastName') {
            if ($this->getShippingAddress() && $this->getShippingAddress()->lastName) {
                return $this->shippingAddress->lastName;
            }

            return '';
        }

        if ($attribute === 'transactionReference') {
            $transactions = $this->getTransactions();
            if ($transactions) {
                return implode(' ', array_map(function($transaction) {
                    return $transaction->reference;
                }, $transactions));
            }

            return '';
        }


        return parent::getSearchKeywords($attribute);
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected static function defineSources(string $context = null): array
    {
        $sources = [
            '*' => [
                'key' => '*',
                'label' => Craft::t('commerce', 'All Orders'),
                'criteria' => ['isCompleted' => true],
                'defaultSort' => ['dateOrdered', 'desc']
            ]
        ];

        $sources[] = ['heading' => Craft::t('commerce', 'Order Status')];

        foreach (Plugin::getInstance()->getOrderStatuses()->getAllOrderStatuses() as $orderStatus) {
            $key = 'orderStatus:'.$orderStatus->handle;
            $sources[] = [
                'key' => $key,
                'status' => $orderStatus->color,
                'label' => $orderStatus->name,
                'criteria' => ['orderStatus' => $orderStatus],
                'defaultSort' => ['dateOrdered', 'desc']
            ];
        }

        $sources[] = ['heading' => Craft::t('commerce', 'Carts')];

        $edge = new \DateTime();
        $interval = new \DateInterval('PT1H');
        $interval->invert = 1;
        $edge->add($interval);

        $sources[] = [
            'key' => 'carts:active',
            'label' => Craft::t('commerce', 'Active Carts'),
            'criteria' => ['updatedAfter' => $edge, 'isCompleted' => 'not 1'],
            'defaultSort' => ['commerce_orders.dateUpdated', 'asc']
        ];

        $sources[] = [
            'key' => 'carts:inactive',
            'label' => Craft::t('commerce', 'Inactive Carts'),
            'criteria' => ['updatedBefore' => $edge, 'isCompleted' => 'not 1'],
            'defaultSort' => ['commerce_orders.dateUpdated', 'desc']
        ];

        return $sources;
    }

    /**
     * @inheritdoc
     */
    protected static function defineActions(string $source = null): array
    {
        $actions = [];

        if (Craft::$app->getUser()->checkPermission('commerce-manageOrders')) {
            $elementService = Craft::$app->getElements();
            $deleteAction = $elementService->createAction(
                [
                    'type' => Delete::class,
                    'confirmationMessage' => Craft::t('commerce', 'Are you sure you want to delete the selected orders?'),
                    'successMessage' => Craft::t('commerce', 'Orders deleted.'),
                ]
            );
            $actions[] = $deleteAction;

            // Only allow mass updating order status when all selected are of the same status, and not carts.
            $isStatus = strpos($source, 'orderStatus:');

            if ($isStatus === 0) {
                $updateOrderStatusAction = $elementService->createAction([
                    'type' => UpdateOrderStatus::class
                ]);
                $actions[] = $updateOrderStatusAction;
            }
        }

        return $actions;
    }

    /**
     * @inheritdoc
     */
    protected static function defineTableAttributes(): array
    {
        return [
            'number' => ['label' => Craft::t('commerce', 'Number')],
            'id' => ['label' => Craft::t('commerce', 'ID')],
            'orderStatus' => ['label' => Craft::t('commerce', 'Status')],
            'totalPrice' => ['label' => Craft::t('commerce', 'Total')],
            'totalPaid' => ['label' => Craft::t('commerce', 'Total Paid')],
            'totalDiscount' => ['label' => Craft::t('commerce', 'Total Discount')],
            'totalShippingCost' => ['label' => Craft::t('commerce', 'Total Shipping')],
            'dateOrdered' => ['label' => Craft::t('commerce', 'Date Ordered')],
            'datePaid' => ['label' => Craft::t('commerce', 'Date Paid')],
            'dateCreated' => ['label' => Craft::t('commerce', 'Date Created')],
            'dateUpdated' => ['label' => Craft::t('commerce', 'Date Updated')],
            'email' => ['label' => Craft::t('commerce', 'Email')],
            'shippingFullName' => ['label' => Craft::t('commerce', 'Shipping Full Name')],
            'billingFullName' => ['label' => Craft::t('commerce', 'Billing Full Name')],
            'shippingBusinessName' => ['label' => Craft::t('commerce', 'Shipping Business Name')],
            'billingBusinessName' => ['label' => Craft::t('commerce', 'Billing Business Name')],
            'shippingMethodName' => ['label' => Craft::t('commerce', 'Shipping Method')],
            'gatewayName' => ['label' => Craft::t('commerce', 'Gateway')]
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function defineDefaultTableAttributes(string $source = null): array
    {
        $attributes = ['number'];

        if (0 !== strpos($source, 'carts:')) {
            $attributes[] = 'orderStatus';
            $attributes[] = 'totalPrice';
            $attributes[] = 'dateOrdered';
            $attributes[] = 'totalPaid';
            $attributes[] = 'datePaid';
        } else {
            $attributes[] = 'dateUpdated';
            $attributes[] = 'totalPrice';
        }

        return $attributes;
    }

    /**
     * @inheritdoc
     */
    protected static function defineSortOptions(): array
    {
        return [
            'number' => Craft::t('commerce', 'Number'),
            'id' => Craft::t('commerce', 'ID'),
            'orderStatusId' => Craft::t('commerce', 'Order Status'),
            'totalPrice' => Craft::t('commerce', 'Total Payable'),
            'totalPaid' => Craft::t('commerce', 'Total Paid'),
            'dateOrdered' => Craft::t('commerce', 'Date Ordered'),
            'commerce_orders.dateUpdated' => Craft::t('commerce', 'Date Updated'),
            'datePaid' => Craft::t('commerce', 'Date Paid')
        ];
    }

    // Private Methods
    // =========================================================================

    /**
     * Updates the adjustments, including deleting the old ones.
     *
     * @return null
     */
    private function _updateAdjustments()
    {
        $previousAdjustments = OrderAdjustmentRecord::find()
            ->where(['orderId' => $this->id])
            ->all();

        $newAdjustmentIds = [];

        foreach ($this->getAdjustments() as $adjustment) {
            // Don't run validation as validation of the adjustments should happen before saving the order
            Plugin::getInstance()->getOrderAdjustments()->saveOrderAdjustment($adjustment, false);
            $newAdjustmentIds[] = $adjustment->id;
        }

        foreach ($previousAdjustments as $previousAdjustment) {
            if (!in_array($previousAdjustment->id, $newAdjustmentIds, false)) {
                $previousAdjustment->delete();
            }
        }
    }

    /**
     * Updates the line items, including deleting the old ones.
     */
    private function _updateLineItems()
    {
        $previousLineItems = LineItemRecord::find()
            ->where(['orderId' => $this->id])
            ->all();

        $newLineItemIds = [];

        foreach ($this->getLineItems() as $lineItem) {
            // Don't run validation as validation of the line item should happen before saving the order
            Plugin::getInstance()->getLineItems()->saveLineItem($lineItem, false);
            $newLineItemIds[] = $lineItem->id;
        }

        foreach ($previousLineItems as $previousLineItem) {
            if (!in_array($previousLineItem->id, $newLineItemIds, false)) {
                $previousLineItem->delete();
            }
        }
    }
}
