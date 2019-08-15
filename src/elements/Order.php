<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\elements;

use Craft;
use craft\base\Element;
use craft\commerce\base\AdjusterInterface;
use craft\commerce\base\Gateway;
use craft\commerce\base\GatewayInterface;
use craft\commerce\base\OrderDeprecatedTrait;
use craft\commerce\base\OrderValidatorsTrait;
use craft\commerce\base\ShippingMethodInterface;
use craft\commerce\elements\actions\UpdateOrderStatus;
use craft\commerce\elements\db\OrderQuery;
use craft\commerce\errors\OrderStatusException;
use craft\commerce\events\LineItemEvent;
use craft\commerce\helpers\Currency;
use craft\commerce\helpers\Order as OrderHelper;
use craft\commerce\models\Address;
use craft\commerce\models\Customer;
use craft\commerce\models\LineItem;
use craft\commerce\models\OrderAdjustment;
use craft\commerce\models\OrderHistory;
use craft\commerce\models\OrderStatus;
use craft\commerce\models\PaymentSource;
use craft\commerce\models\Settings;
use craft\commerce\models\ShippingMethod;
use craft\commerce\models\Transaction;
use craft\commerce\Plugin;
use craft\commerce\records\LineItem as LineItemRecord;
use craft\commerce\records\Order as OrderRecord;
use craft\commerce\records\OrderAdjustment as OrderAdjustmentRecord;
use craft\db\Query;
use craft\elements\actions\Delete;
use craft\elements\actions\Restore;
use craft\elements\db\ElementQueryInterface;
use craft\elements\User;
use craft\errors\ElementNotFoundException;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use craft\helpers\Template;
use craft\helpers\UrlHelper;
use DateInterval;
use DateTime;
use Throwable;
use yii\base\Exception;
use yii\base\InvalidArgumentException;
use yii\base\InvalidConfigException;

/**
 * Order or Cart model.
 *
 * @property OrderAdjustment[] $adjustments
 * @property bool $shouldRecalculateAdjustments
 * @property string $email the email for this order
 * @property LineItem[] $lineItems
 * @property Address $billingAddress
 * @property Address $shippingAddress
 * @property PaymentSource|null $paymentSource
 * @property string $paymentCurrency the payment currency for this order
 * @property-read ShippingMethod[] $availableShippingMethods
 * @property-read bool $activeCart Is the current order the same as the active cart
 * @property-read Customer $customer
 * @property-read Gateway $gateway
 * @property-read OrderStatus $orderStatus
 * @property-read float $outstandingBalance The balance amount to be paid on the Order
 * @property-read ShippingMethodInterface $shippingMethod
 * @property-read ShippingMethodInterface $shippingMethodId
 * @property-read User|null $user
 * @property-read OrderAdjustment[] $orderAdjustments
 * @property-read string $pdfUrl the URL to the order’s PDF invoice
 * @property-read float|int $adjustmentSubtotal the total of adjustments made to order
 * @property-read float $adjustmentsTotal
 * @property-read OrderHistory[] $histories order histories
 * @property-read bool $isPaid if the order is paid
 * @property-read bool $isUnpaid if the order is not paid
 * @property-read float $itemTotal
 * @property-read int $itemSubtotal the total of all line item subtotals
 * @property-read bool $isActiveCart the order has the same ID as the current sessions cart
 * @property-read bool $isEmpty the order has no line items with any qty
 * @property-read null|Transaction $lastTransaction The last transaction on the order.
 * @property-read Transaction[] $nestedTransactions transactions for the order that have child transactions set on them
 * @property-read string $paidStatus the order’s paid status
 * @property-read string $paidStatusHtml the order’s paid status as HTML
 * @property-read string $shortNumber
 * @property-read float $totalPaid the total `purchase` and `captured` transactions belonging to this order
 * @property-read float $total
 * @property-read float $totalPrice
 * @property-read int $totalSaleAmount the total sale amount
 * @property-read float $totalTaxablePrice
 * @property-read int $totalQty the total number of items
 * @property-read int $totalWeight
 * @property-read Transaction[] $transactions
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Order extends Element
{
    use OrderValidatorsTrait;
    use OrderDeprecatedTrait;

    // Constants
    // =========================================================================

    const PAID_STATUS_PAID = 'paid';
    const PAID_STATUS_PARTIAL = 'partial';
    const PAID_STATUS_UNPAID = 'unpaid';

    /**
     * @event \yii\base\Event This event is raised before a line item has been added to the order
     *
     * Plugins can get notified before a new line item has been added to the order
     *
     * ```php
     * use craft\commerce\elements\Order;
     * use yii\events\CancelableEvent
     *
     * Event::on(Order::class, Order::EVENT_AFTER_ADD_LINE_ITEM, function(CancelableEvent $e) {
     *     $lineItem = $e->lineItem;
     *     $isNew = $e->isNew;
     *     $isValid = $e->isValid;
     *     // ...
     * });
     * ```
     */
    const EVENT_BEFORE_ADD_LINE_ITEM = 'beforeAddLineItemToOrder';

    /**
     * @event \yii\base\Event This event is raised when a line item is added to the order
     *
     * Plugins can get notified after a line item has been added to the order
     *
     * ```php
     * use craft\commerce\elements\Order;
     * use yii\events\Event;
     *
     * Event::on(Order::class, Order::EVENT_AFTER_ADD_LINE_ITEM, function(Event $e) {
     *     $lineItem = $e->lineItem;
     *     $isNew = $e->isNew;
     *     // ...
     * });
     * ```
     */
    const EVENT_AFTER_ADD_LINE_ITEM = 'afterAddLineItemToOrder';

    /**
     * @event \yii\base\Event This event is raised when a line item is removed from the order
     *
     * Plugins can get notified after a line item has been removed from the order
     *
     * ```php
     * use craft\commerce\elements\Order;
     * use yii\base\Event;
     *
     * Event::on(Order::class, Order::EVENT_AFTER_REMOVE_LINE_ITEM, function(Event $e) {
     *     $lineItem = $e->lineItem;
     *     $isNew = $e->isNew;
     *     // ...
     * });
     * ```
     */
    const EVENT_AFTER_REMOVE_LINE_ITEM = 'afterRemoveLineItemToOrder';

    /**
     * @event \yii\base\Event This event is raised when an order is completed
     *
     * Plugins can get notified before an order is completed
     *
     * ```php
     * use craft\commerce\elements\Order;
     * use yii\base\Event;
     *
     * Event::on(Order::class, Order::EVENT_BEFORE_COMPLETE_ORDER, function(Event $e) {
     *     // @var Order $order
     *     $order = $e->sender;
     *     // ...
     * });
     * ```
     */
    const EVENT_BEFORE_COMPLETE_ORDER = 'beforeCompleteOrder';

    /**
     * @event \yii\base\Event This event is raised after an order is completed
     *
     * Plugins can get notified after an order is completed
     *
     * ```php
     * use craft\commerce\elements\Order;
     * use yii\base\Event;
     *
     * Event::on(Order::class, Order::EVENT_AFTER_COMPLETE_ORDER, function(Event $e) {
     *     // @var Order $order
     *     $order = $e->sender;
     *     // ...
     * });
     * ```
     */
    const EVENT_AFTER_COMPLETE_ORDER = 'afterCompleteOrder';

    /**
     * @event \yii\base\Event This event is raised after an order is paid and completed
     *
     * Plugins can get notified after an order is paid and completed
     *
     * ```php
     * use craft\commerce\elements\Order;
     * use yii\base\Event;
     *
     * Event::on(Order::class, Order::EVENT_AFTER_ORDER_PAID, function(Event $e) {
     *     // @var Order $order
     *     $order = $e->sender;
     *     // ...
     * });
     * ```
     */
    const EVENT_AFTER_ORDER_PAID = 'afterOrderPaid';

    // Properties
    // =========================================================================

    /**
     * @var string Number
     */
    public $number;

    /**
     * @var string Reference
     */
    public $reference;

    /**
     * @var string Coupon Code
     */
    public $couponCode;

    /**
     * @var bool Is completed
     */
    public $isCompleted = false;

    /**
     * @var DateTime Date ordered
     */
    public $dateOrdered;

    /**
     * @var DateTime Date paid
     */
    public $datePaid;

    /**
     * @var string Currency
     */
    public $currency;

    /**
     * @var int|null Gateway ID
     */
    public $gatewayId;

    /**
     * @var string Last IP
     */
    public $lastIp;

    /**
     * @var string Order locale
     */
    public $orderLanguage;

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
     * @var bool Whether shipping address should be made primary
     */
    public $makePrimaryShippingAddress;

    /**
     * @var bool Whether billing address should be made primary
     */
    public $makePrimaryBillingAddress;

    /**
     * @var bool Whether shipping address should be set to the same address as billing
     */
    public $shippingSameAsBilling;

    /**
     * @var bool Whether billing address should be set to the same address as shipping
     */
    public $billingSameAsShipping;

    /**
     * @var string Shipping Method Handle
     */
    public $shippingMethodHandle;

    /**
     * @var int Customer ID
     */
    public $customerId;

    /**
     * @var bool Register the email on order completion
     */
    public $registerUserOnOrderComplete;

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
     * @var string
     */
    private $_paymentCurrency;

    /**
     * @var int|null Payment source ID
     */
    public $paymentSourceId;

    /**
     * @var string Email
     */
    private $_email;

    /**
     * @var bool Should the order recalculate?
     */
    private $_recalculate = true;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        // Set default addresses on the order
        if (!$this->isCompleted && Plugin::getInstance()->getSettings()->autoSetNewCartAddresses) {
            $hasPrimaryShippingAddress = !$this->shippingAddressId && $this->getCustomer() && $this->getCustomer()->primaryShippingAddressId;
            if ($hasPrimaryShippingAddress && ($address = Plugin::getInstance()->getAddresses()->getAddressById($this->getCustomer()->primaryShippingAddressId)) !== null) {
                $this->setShippingAddress($address);
            }
            $hasPrimaryBillingAddress = !$this->billingAddressId && $this->getCustomer() && $this->getCustomer()->primaryBillingAddressId;
            if ($hasPrimaryBillingAddress && ($address = Plugin::getInstance()->getAddresses()->getAddressById($this->getCustomer()->primaryBillingAddressId)) !== null) {
                $this->setBillingAddress($address);
            }
        }

        if (!$this->orderLanguage) {
            $this->orderLanguage = Craft::$app->language;
        }

        return parent::init();
    }

    /**
     * @return null|string
     */
    public static function displayName(): string
    {
        return Craft::t('commerce', 'Orders');
    }

    /**
     * @inheritdoc
     */
    public function __toString()
    {
        return $this->reference ?: $this->getShortNumber();
    }

    /**
     * @inheritdoc
     */
    public function beforeValidate()
    {
        // Set default gateway if none present and no payment source selected
        if (!$this->gatewayId && !$this->paymentSourceId) {
            $gateways = Plugin::getInstance()->getGateways()->getAllCustomerEnabledGateways();
            if (count($gateways)) {
                $this->gatewayId = key($gateways);
            }
        }

        // Get the customer ID from the session
        if (!$this->customerId && !Craft::$app->request->isConsoleRequest) {
            $this->customerId = Plugin::getInstance()->getCustomers()->getCustomerId();
        }

        $customer = Plugin::getInstance()->getCustomers()->getCustomerById($this->customerId);
        if ($email = $customer->getEmail()) {
            $this->setEmail($email);
        }

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
        $names[] = 'adjustmentSubtotal';
        $names[] = 'adjustmentsTotal';
        $names[] = 'email';
        $names[] = 'itemSubtotal';
        $names[] = 'itemTotal';
        $names[] = 'lineItems';
        $names[] = 'orderAdjustments';
        $names[] = 'outstandingBalance';
        $names[] = 'shortNumber';
        $names[] = 'totalPaid';
        $names[] = 'total';
        $names[] = 'totalPrice';
        $names[] = 'totalQty';
        $names[] = 'totalSaleAmount';
        $names[] = 'totalTaxablePrice';
        $names[] = 'totalWeight';
        return $names;
    }

    /**
     * @inheritdoc
     */
    public function extraFields()
    {
        $names = parent::extraFields();
        $names[] = 'adjustments';
        $names[] = 'billingAddress';
        $names[] = 'customer';
        $names[] = 'gateway';
        $names[] = 'histories';
        $names[] = 'nestedTransactions';
        $names[] = 'orderStatus';
        $names[] = 'pdfUrl';
        $names[] = 'shippingAddress';
        $names[] = 'shippingMethod';
        $names[] = 'shippingMethodId';
        $names[] = 'transactions';
        return $names;
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        $rules = parent::rules();

        // Address models are valid
        $rules[] = [
            ['billingAddress', 'shippingAddress'], 'validateAddress'
        ]; // from OrderValidatorTrait

        // Do addresses  belong to the customer of the order (only checked if the order is a cart)
        $rules[] = [
            ['billingAddress', 'shippingAddress'], 'validateAddressBelongsToOrdersCustomer', 'when' => function($model) {
                /** @var Order $model */
                return !$model->isCompleted;
            }
        ]; // from OrderValidatorTrait

        // Are the addresses both being set to each other.
        $rules[] = [
            ['billingAddress', 'shippingAddress'], 'validateAddressReuse', 'when' => function($model) {
                /** @var Order $model */
                return !$model->isCompleted;
            }
        ]; // from OrderValidatorTrait

        // Line items are valid?
        $rules[] = [['lineItems'], 'validateLineItems']; // from OrderValidatorTrait

        // Coupon Code valid?
        $rules[] = [['couponCode'], 'validateCouponCode']; // from OrderValidatorTrait

        $rules[] = [['gatewayId'], 'number', 'integerOnly' => true];
        $rules[] = [['gatewayId'], 'validateGatewayId']; // OrderValidatorsTrait
        $rules[] = [['shippingAddressId'], 'number', 'integerOnly' => true];
        $rules[] = [['billingAddressId'], 'number', 'integerOnly' => true];

        $rules[] = [['paymentCurrency'], 'validatePaymentCurrency']; // OrderValidatorTrait

        $rules[] = [['paymentSourceId'], 'number', 'integerOnly' => true];
        $rules[] = [['paymentSourceId'], 'validatePaymentSourceId']; // OrderValidatorTrait
        $rules[] = [['email'], 'email'];

        return $rules;
    }

    /**
     * @deprecated
     */
    public function updateOrderPaidTotal()
    {
        Craft::$app->getDeprecator()->log('Order::updateOrderPaidTotal()', 'The Order::updateOrderPaidTotal() function has been deprecated. Use Order::Order::updateOrderPaidInformation() instead');

        return $this->updateOrderPaidInformation();
    }

    /**
     * Updates the paid status and paid date of the order, and marks as complete if the order is paid or authorized.
     */
    public function updateOrderPaidInformation()
    {
        $justPaid = !$this->hasOutstandingBalance() && $this->datePaid === null;

        if ($justPaid) {
            $this->datePaid = Db::prepareDateForDb(new DateTime());
        }

        // Lock for recalculation
        $originalShouldRecalculate = $this->getShouldRecalculateAdjustments();
        $this->setShouldRecalculateAdjustments(false);

        // Saving the order will update the datePaid as set above and also update the paidStatus.
        Craft::$app->getElements()->saveElement($this, false);

        // If the order is now paid or authorized in full, lets mark it as complete if it has not already been.
        if (!$this->isCompleted) {
            $totalPaid = Plugin::getInstance()->getPayments()->getTotalPaidForOrder($this);
            $totalAuthorized = Plugin::getInstance()->getPayments()->getTotalAuthorizedForOrder($this);
            if ($totalAuthorized >= $this->getTotalPrice() || $totalPaid >= $this->getTotalPrice()) {
                $this->markAsComplete();
            }
        }

        if ($justPaid && $this->hasEventHandlers(self::EVENT_AFTER_ORDER_PAID)) {
            $this->trigger(self::EVENT_AFTER_ORDER_PAID);
        }

        // restore recalculation lock state
        $this->setShouldRecalculateAdjustments($originalShouldRecalculate);
    }

    /**
     * Returns the total price of the order, minus any tax adjustments.
     *
     * @return float
     */
    public function getTotalTaxablePrice(): float
    {
        $itemTotal = $this->getItemSubtotal();

        $allNonIncludedAdjustmentsTotal = $this->getAdjustmentsTotal();
        $taxAdjustments = $this->getAdjustmentsTotalByType('tax');
        $includedTaxAdjustments = $this->getAdjustmentsTotalByType('tax', true);

        return $itemTotal + $allNonIncludedAdjustmentsTotal - ($taxAdjustments + $includedTaxAdjustments);
    }

    /**
     * @return bool
     */
    public function getShouldRecalculateAdjustments(): bool
    {
        return $this->_recalculate;
    }

    /**
     * @param bool $value
     */
    public function setShouldRecalculateAdjustments(bool $value)
    {
        $this->_recalculate = $value;
    }

    /**
     * Marks the order as complete and sets the default order status, then saves the order.
     *
     * @return bool
     * @throws OrderStatusException
     * @throws Exception
     * @throws Throwable
     * @throws ElementNotFoundException
     */
    public function markAsComplete(): bool
    {
        // Use a mutex to make sure we check the order is not already complete due to a race condition.
        $lockName = 'orderComplete:' . $this->id;
        $mutex = Craft::$app->getMutex();
        if (!$mutex->acquire($lockName, 5)) {
            throw new Exception('Unable to acquire a lock for completion of Order: ' . $this->id);
        }

        // Now that we have a lock, make sure this order is not already completed.
        if ($this->isCompleted) {
            $mutex->release($lockName);
            return true;
        }

        // Try to catch where the order could be marked as completed twice at the same time, and thus cause a race condition.
        $completedInDb = (new Query())
            ->select('id')
            ->from(['{{%commerce_orders}}'])
            ->where(['isCompleted' => true])
            ->andWhere(['id' => $this->id])
            ->exists();

        if ($completedInDb) {
            $mutex->release($lockName);
            return true;
        }
        // Release after we have confirmed this order is not already complete

        $mutex->release($lockName);

        $this->isCompleted = true;
        $this->dateOrdered = Db::prepareDateForDb(new DateTime());
        $orderStatus = Plugin::getInstance()->getOrderStatuses()->getDefaultOrderStatusForOrder($this);

        // If the order status returned was overridden by a plugin, use the configured default order status if they give us a bogus one with no ID.
        if ($orderStatus && $orderStatus->id) {
            $this->orderStatusId = $orderStatus->id;
        } else {
            throw new OrderStatusException('Could not find a valid default order status.');
        }

        $referenceTemplate = Plugin::getInstance()->getSettings()->orderReferenceFormat;

        try {
            $this->reference = Craft::$app->getView()->renderObjectTemplate($referenceTemplate, $this);
        } catch (Throwable $exception) {
            Craft::error('Unable to generate order completion reference for order ID: ' . $this->id . ', with format: ' . $referenceTemplate . ', error: ' . $exception->getMessage());
            throw $exception;
        }

        // Raising the 'beforeCompleteOrder' event
        if ($this->hasEventHandlers(self::EVENT_BEFORE_COMPLETE_ORDER)) {
            $this->trigger(self::EVENT_BEFORE_COMPLETE_ORDER);
        }

        if (Craft::$app->getElements()->saveElement($this, false)) {

            $this->afterOrderComplete();

            return true;
        }

        Craft::error(Craft::t('commerce', 'Could not mark order {number} as complete. Order save failed during order completion with errors: {order}',
            ['number' => $this->number, 'order' => json_encode($this->errors)]), __METHOD__);

        return false;
    }

    /**
     * Called after the order successfully completes
     */
    public function afterOrderComplete()
    {
        // Run order complete handlers directly.
        Plugin::getInstance()->getDiscounts()->orderCompleteHandler($this);
        Plugin::getInstance()->getCustomers()->orderCompleteHandler($this);

        foreach ($this->getLineItems() as $lineItem) {
            if ($lineItem->getPurchasable()) {
                $lineItem->getPurchasable()->afterOrderComplete($this, $lineItem);
            }
        }

        // Raising the 'afterCompleteOrder' event
        if ($this->hasEventHandlers(self::EVENT_AFTER_COMPLETE_ORDER)) {
            $this->trigger(self::EVENT_AFTER_COMPLETE_ORDER);
        }
    }

    /**
     * Removes a specific line item from the order.
     *
     * @param LineItem $lineItem
     */
    public function removeLineItem(LineItem $lineItem)
    {
        $lineItems = $this->getLineItems();
        foreach ($lineItems as $key => $item) {
            if ($lineItem->id == $item->id || $lineItem === $item) {
                unset($lineItems[$key]);
                $this->setLineItems($lineItems);
            }
        }

        // Raising the 'afterRemoveLineItemToOrder' event
        if ($this->hasEventHandlers(self::EVENT_AFTER_REMOVE_LINE_ITEM)) {
            $this->trigger(self::EVENT_AFTER_REMOVE_LINE_ITEM, new LineItemEvent([
                'lineItem' => $lineItem,
            ]));
        }
    }

    /**
     * Adds a line item to the order. Updates the line item if the ID of that line item is already in the cart.
     *
     * @param LineItem $lineItem
     */
    public function addLineItem($lineItem)
    {
        $lineItems = $this->getLineItems();
        $isNew = !$lineItem->id;

        if ($isNew && $this->hasEventHandlers(self::EVENT_BEFORE_ADD_LINE_ITEM)) {
            $lineItemEvent = new LineItemEvent(compact('lineItem', 'isNew'));
            $this->trigger(self::EVENT_BEFORE_ADD_LINE_ITEM, $lineItemEvent);

            if (!$lineItemEvent->isValid) {
                return;
            }
        }

        $replaced = false;
        foreach ($lineItems as $key => $item) {
            if ($lineItem->id && $item->id == $lineItem->id) {
                $lineItems[$key] = $lineItem;
                $replaced = true;
            }
        }

        if (!$replaced) {
            $lineItems[] = $lineItem;
        }

        $this->setLineItems($lineItems);

        // Raising the 'afterAddLineItemToOrder' event
        if ($this->hasEventHandlers(self::EVENT_AFTER_ADD_LINE_ITEM)) {
            $this->trigger(self::EVENT_AFTER_ADD_LINE_ITEM, new LineItemEvent([
                'lineItem' => $lineItem,
                'isNew' => !$replaced
            ]));
        }
    }

    /**
     * Regenerates all adjusters and update line item and order totals.
     *
     * @throws Exception
     */
    public function recalculate()
    {
        // Check if the order needs to recalculated
        if (!$this->id || $this->isCompleted || !$this->getShouldRecalculateAdjustments() || $this->hasErrors()) {
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

        // This is run in a validation, but need to run again incase the options
        // data was changed on population of the line item by a plugin.
        if (OrderHelper::mergeDuplicateLineItems($this)) {
            $lineItemRemoved = true;
        }

        if ($lineItemRemoved) {
            $this->recalculate();
            return;
        }

        foreach (Plugin::getInstance()->getOrderAdjustments()->getAdjusters() as $adjuster) {
            /** @var AdjusterInterface $adjuster */
            $adjuster = new $adjuster();
            $adjustments = $adjuster->adjust($this);
            $this->setAdjustments(array_merge($this->getAdjustments(), $adjustments));
        }

        // Since shipping adjusters run on the original price, pre discount, let's recalculate
        // if the currently selected shipping method is now not available after adjustments have run.
        $availableMethods = $this->getAvailableShippingMethods();
        if ($this->shippingMethodHandle) {
            if (!isset($availableMethods[$this->shippingMethodHandle]) || empty($availableMethods)) {
                $this->shippingMethodHandle = null;
                $this->recalculate();

                return;
            }
        }
    }

    /**
     * @return ShippingMethodInterface[]|\craft\commerce\base\ShippingMethod[]
     */
    public function getAvailableShippingMethods(): array
    {
        return Plugin::getInstance()->getShippingMethods()->getAvailableShippingMethods($this);
    }

    /**
     * @return float
     */
    public function getItemTotal(): float
    {
        $total = 0;

        foreach ($this->getLineItems() as $lineItem) {
            $total += $lineItem->getTotal();
        }

        return $total;
    }

    /**
     * @inheritdoc
     */
    public function afterSave(bool $isNew)
    {
        // TODO: Move the recalculate to somewhere else. Saving should be for saving only
        // Right now orders always recalc when saved and not completed but that shouldn't always be the case.
        $this->recalculate();

        if (!$isNew) {
            $orderRecord = OrderRecord::findOne($this->id);

            if (!$orderRecord) {
                throw new Exception('Invalid order ID: ' . $this->id);
            }
        } else {
            $orderRecord = new OrderRecord();
            $orderRecord->id = $this->id;
        }

        $oldStatusId = $orderRecord->orderStatusId;

        $orderRecord->number = $this->number;
        $orderRecord->reference = $this->reference;
        $orderRecord->itemTotal = $this->getItemTotal();
        $orderRecord->email = $this->getEmail() ?? '';
        $orderRecord->isCompleted = $this->isCompleted;
        $orderRecord->dateOrdered = $this->dateOrdered;
        $orderRecord->datePaid = $this->datePaid ?: null;
        $orderRecord->shippingMethodHandle = $this->shippingMethodHandle;
        $orderRecord->paymentSourceId = $this->getPaymentSource() ? $this->getPaymentSource()->id : null;
        $orderRecord->gatewayId = $this->gatewayId;
        $orderRecord->orderStatusId = $this->orderStatusId;
        $orderRecord->couponCode = $this->couponCode;
        $orderRecord->total = $this->getTotal();
        $orderRecord->totalPrice = $this->getTotalPrice();
        $orderRecord->totalPaid = $this->getTotalPaid();
        $orderRecord->currency = $this->currency;
        $orderRecord->lastIp = $this->lastIp;
        $orderRecord->orderLanguage = $this->orderLanguage;
        $orderRecord->paymentCurrency = $this->paymentCurrency;
        $orderRecord->customerId = $this->customerId;
        $orderRecord->registerUserOnOrderComplete = $this->registerUserOnOrderComplete;
        $orderRecord->returnUrl = $this->returnUrl;
        $orderRecord->cancelUrl = $this->cancelUrl;
        $orderRecord->message = $this->message;
        $orderRecord->paidStatus = $this->getPaidStatus();

        $customer = $this->getCustomer();
        $existingAddresses = $customer ? $customer->getAddresses() : [];

        if ($this->shippingSameAsBilling) {
            $this->setShippingAddress($this->getBillingAddress());
        }

        if ($this->billingSameAsShipping) {
            $this->setBillingAddress($this->getShippingAddress());
        }

        // Save shipping address, it has already been validated.
        if ($shippingAddress = $this->getShippingAddress()) {
            // We need to only save the address to the customers address book while it is a cart
            if ($customer && !$this->isCompleted) {
                Plugin::getInstance()->getCustomers()->saveAddress($shippingAddress, $customer, false);
            } else {
                Plugin::getInstance()->getAddresses()->saveAddress($shippingAddress, false);
            }

            $orderRecord->shippingAddressId = $shippingAddress->id;
            $this->setShippingAddress($shippingAddress);
        }

        // Save billing address, it has already been validated.
        if ($billingAddress = $this->getBillingAddress()) {
            // We need to only save the address to the customers address book while it is a cart
            if ($customer && !$this->isCompleted) {
                Plugin::getInstance()->getCustomers()->saveAddress($billingAddress, $customer, false);
            } else {
                Plugin::getInstance()->getAddresses()->saveAddress($billingAddress, false);
            }

            $orderRecord->billingAddressId = $billingAddress->id;
            $this->setBillingAddress($billingAddress);
        }


        $orderRecord->save(false);

        $updateCustomer = false;

        if ($customer) {
            if ($this->makePrimaryBillingAddress || empty($existingAddresses) || !$customer->primaryBillingAddressId) {
                $customer->primaryBillingAddressId = $orderRecord->billingAddressId;
                $updateCustomer = true;
            }

            if ($this->makePrimaryShippingAddress || empty($existingAddresses) || !$customer->primaryShippingAddressId) {
                $customer->primaryShippingAddressId = $orderRecord->shippingAddressId;
                $updateCustomer = true;
            }

            if ($updateCustomer) {
                Plugin::getInstance()->getCustomers()->saveCustomer($customer);
            }
        }
        $this->_saveAdjustments();

        $this->_saveLineItems();


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
     * @inheritdoc
     */
    public function getIsEditable(): bool
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
        return Template::raw("<a href='" . $this->getCpEditUrl() . "'>" . ($this->reference ?: $this->getShortNumber()) . '</a>');
    }

    /**
     * @inheritdoc
     */
    public function getCpEditUrl(): string
    {
        return UrlHelper::cpUrl('commerce/orders/' . $this->id);
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
            $pdf = Plugin::getInstance()->getPdf()->renderPdfForOrder($this, $option);
            if ($pdf) {
                $path = "commerce/downloads/pdf?number={$this->number}" . ($option ? "&option={$option}" : '');
                $path = Craft::$app->getConfig()->getGeneral()->actionTrigger . '/' . trim($path, '/');
                $url = UrlHelper::siteUrl($path);
            }
        } catch (\Exception $exception) {
            Craft::error($exception->getMessage());
            return null;
        }

        return $url;
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
     * @return string|null
     */
    public function getEmail()
    {
        if ($this->getCustomer() && $this->getCustomer()->getUser()) {
            $this->setEmail($this->getCustomer()->getUser()->email);
        }

        return $this->_email ?? null;
    }

    /**
     * Sets the orders email address. Will have no affect if the order's customer is a registered user.
     *
     * @param string|null $value
     */
    public function setEmail($value)
    {
        $this->_email = $value;
    }

    /**
     * @return bool
     */
    public function getIsPaid(): bool
    {
        return !$this->hasOutstandingBalance() && $this->isCompleted;
    }

    /**
     * What is the status of the orders payment
     *
     * @return string
     */
    public function getPaidStatus(): string
    {
        if ($this->getIsPaid()) {
            return self::PAID_STATUS_PAID;
        }

        if ($this->totalPaid > 0) {
            return self::PAID_STATUS_PARTIAL;
        }

        return self::PAID_STATUS_UNPAID;
    }

    /**
     * Paid status represented as HTML
     *
     * @return string
     */
    public function getPaidStatusHtml(): string
    {
        switch ($this->getPaidStatus()) {
            case self::PAID_STATUS_PAID:
            {
                return '<span class="commerceStatusLabel"><span class="status green"></span> ' . Craft::t('commerce', 'Paid') . '</span>';
            }
            case self::PAID_STATUS_PARTIAL:
            {
                return '<span class="commerceStatusLabel"><span class="status orange"></span> ' . Craft::t('commerce', 'Partial') . '</span>';
            }
            case self::PAID_STATUS_UNPAID:
            {
                return '<span class="commerceStatusLabel"><span class="status red"></span> ' . Craft::t('commerce', 'Unpaid') . '</span>';
            }
        }

        return '';
    }

    /**
     * Returns the raw total of the order, which is the total of all line items and adjustments. This number can be negative, so it is not the price of the order.
     *
     * @return float
     * @see Order::getTotalPrice() The actual total price of the order.
     *
     */
    public function getTotal(): float
    {
        return Currency::round($this->getItemSubtotal() + $this->getAdjustmentsTotal());
    }

    /**
     * Get the total price of the order, whose minimum value is enforced by the configured {@link Settings::minimumTotalPriceStrategy strategy set for minimum total price}.
     *
     * @return float
     */
    public function getTotalPrice(): float
    {
        $total = $this->getItemSubtotal() + $this->getAdjustmentsTotal(); // Don't get the pre-rounded total.
        $strategy = Plugin::getInstance()->getSettings()->minimumTotalPriceStrategy;

        if ($strategy === Settings::MINIMUM_TOTAL_PRICE_STRATEGY_ZERO) {
            return Currency::round(max(0, $total));
        }

        if ($strategy === Settings::MINIMUM_TOTAL_PRICE_STRATEGY_SHIPPING) {
            return Currency::round(max($this->getAdjustmentsTotalByType('shipping'), $total));
        }

        return Currency::round($total);
    }

    /**
     * Returns the difference between the order amount and amount paid.
     *
     * @return float
     */
    public function getOutstandingBalance(): float
    {
        $totalPaid = Currency::round($this->getTotalPaid());
        $totalPrice = $this->getTotalPrice(); // Already rounded

        return $totalPrice - $totalPaid;
    }

    /**
     * @return bool
     */
    public function hasOutstandingBalance()
    {
        return $this->getOutstandingBalance() > 0;
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
    public function getIsUnpaid(): bool
    {
        return $this->hasOutstandingBalance();
    }

    /**
     * Returns whether this order is the user's current active cart.
     *
     * @return bool
     */
    public function getIsActiveCart(): bool
    {
        $cart = Plugin::getInstance()->getCarts()->getCart();

        return ($cart && $cart->id == $this->id);
    }

    /**
     * Returns whether the order has any items in it.
     *
     * @return bool
     */
    public function getIsEmpty(): bool
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
            $lineItems = $this->id ? Plugin::getInstance()->getLineItems()->getAllLineItemsByOrderId($this->id) : [];
            foreach ($lineItems as $lineItem) {
                $lineItem->setOrder($this);
            }
            $this->_lineItems = $lineItems;
        }

        return $this->_lineItems;
    }

    /**
     * @param LineItem[] $lineItems
     */
    public function setLineItems(array $lineItems)
    {
        $this->_lineItems = [];

        foreach ($lineItems as $lineItem) {
            $lineItem->setOrder($this);
        }

        // Lite should only allow one line item while the order is a cart.
        if (Plugin::getInstance()->is(Plugin::EDITION_LITE) && $this->isCompleted == false) {
            if (empty($lineItems)) {
                $this->_lineItems = [];
            } else {
                $last = array_values(array_slice($lineItems, -1))[0];
                $this->_lineItems = [$last];
            }
        } else {
            $this->_lineItems = $lineItems;
        }
    }

    /**
     * @param string|array $types
     * @param bool $included
     * @return float|int
     */
    public function getAdjustmentsTotalByType($types, $included = false)
    {
        $amount = 0;

        if (is_string($types)) {
            $types = StringHelper::split($types);
        }

        foreach ($this->getAdjustments() as $adjustment) {
            if ($adjustment->included == $included && in_array($adjustment->type, $types, false)) {
                $amount += $adjustment->amount;
            }
        }

        return $amount;
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
        if (null === $this->_shippingAddress && $this->shippingAddressId) {
            $this->_shippingAddress = Plugin::getInstance()->getAddresses()->getAddressById($this->shippingAddressId);
        }

        return $this->_shippingAddress;
    }

    /**
     * @param Address|array $address
     */
    public function setShippingAddress($address)
    {
        if (!$address instanceof Address) {
            $address = new Address($address);
        }

        $this->shippingAddressId = $address->id;
        $this->_shippingAddress = $address;
    }

    /**
     * @return Address|null
     */
    public function getBillingAddress()
    {
        if (null === $this->_billingAddress && $this->billingAddressId) {
            $this->_billingAddress = Plugin::getInstance()->getAddresses()->getAddressById($this->billingAddressId);
        }

        return $this->_billingAddress;
    }

    /**
     * @param Address|array $address
     */
    public function setBillingAddress($address)
    {
        if (!$address instanceof Address) {
            $address = new Address($address);
        }

        $this->billingAddressId = $address->id;
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
        $shippingMethods = Plugin::getInstance()->getShippingMethods()->getAvailableShippingMethods($this);

        // Do we have a shipping method available based on the current selection?
        if (isset($shippingMethods[$this->shippingMethodHandle])) {
            return $shippingMethods[$this->shippingMethodHandle];
        }
        $handles = [];

        /** @var ShippingMethod $shippingMethod */
        foreach ($shippingMethods as $shippingMethod) {
            $handles[] = $shippingMethod->getHandle();
        }

        if (!empty($shippingMethods)) {
            /** @var ShippingMethod $firstAvailable */
            $firstAvailable = array_values($shippingMethods)[0];
            if (!$this->shippingMethodHandle || !in_array($this->shippingMethodHandle, $handles, false)) {
                $this->shippingMethodHandle = $firstAvailable->getHandle();
            }
        }

        return $shippingMethods[$this->shippingMethodHandle] ?? null;
    }

    /**
     * @return GatewayInterface|null
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     */
    public function getGateway()
    {
        if ($this->gatewayId === null && $this->paymentSourceId === null) {
            return null;
        }

        $gateway = null;

        // sources before gateways
        if ($this->paymentSourceId) {
            if ($paymentSource = Plugin::getInstance()->getPaymentSources()->getPaymentSourceById($this->paymentSourceId)) {
                $gateway = Plugin::getInstance()->getGateways()->getGatewayById($paymentSource->gatewayId);
            }
        } else {
            $gateway = Plugin::getInstance()->getGateways()->getGatewayById($this->gatewayId);
        }

        if (null === $gateway) {
            throw new InvalidArgumentException("Invalid gateway ID: {$this->gatewayId}");
        }

        return $gateway;
    }

    /**
     * Returns the current payment currency, and defaults to the primary currency if not set.
     *
     * @return string
     */
    public function getPaymentCurrency()
    {
        if ($this->_paymentCurrency === null) {
            $this->_paymentCurrency = Plugin::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrencyIso();
        }

        if ($this->_paymentCurrency) {
            $allPaymentCurrenciesIso = ArrayHelper::getColumn(Plugin::getInstance()->getPaymentCurrencies()->getAllPaymentCurrencies(), 'iso');
            if (!in_array($this->_paymentCurrency, $allPaymentCurrenciesIso, false)) {
                throw new InvalidConfigException('Payment currency not allowed.');
            }
        }

        return $this->_paymentCurrency;
    }

    /**
     * @param string $value the payment currency code
     */
    public function setPaymentCurrency($value)
    {
        $this->_paymentCurrency = $value;
    }

    /**
     * Returns the order's selected payment source if any.
     *
     * @return PaymentSource|null
     * @throws InvalidConfigException if the payment source is being set by a guest customer.
     * @throws InvalidArgumentException if the order is set to an invalid payment source.
     */
    public function getPaymentSource()
    {
        if ($this->paymentSourceId === null) {
            return null;
        }

        if (($user = $this->getUser()) === null) {
            throw new InvalidConfigException('Guest customers can not set a payment source.');
        }

        if (($paymentSource = Plugin::getInstance()->getPaymentSources()->getPaymentSourceByIdAndUserId($this->paymentSourceId, $user->id)) === null) {
            throw new InvalidArgumentException("Invalid payment source ID: {$this->paymentSourceId}");
        }

        return $paymentSource;
    }

    /**
     * Sets the order's selected payment source
     *
     * @param PaymentSource|null $paymentSource
     */
    public function setPaymentSource(PaymentSource $paymentSource)
    {
        $this->paymentSourceId = $paymentSource->id;
        $this->gatewayId = null;
    }

    /**
     * Sets the order's selected gateway id.
     *
     * @param int $gatewayId
     */
    public function setGatewayId(int $gatewayId)
    {
        $this->gatewayId = $gatewayId;
        $this->paymentSourceId = null;
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
        return $this->id ? Plugin::getInstance()->getTransactions()->getAllTransactionsByOrderId($this->id) : [];
    }

    /**
     * @return Transaction|null
     */
    public function getLastTransaction()
    {
        $transactions = $this->getTransactions();
        return count($transactions) ? array_pop($transactions) : null;
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
     * @return OrderQuery The newly created [[OrderQuery]] instance.
     */
    public static function find(): ElementQueryInterface
    {
        return new OrderQuery(static::class);
    }

    /**
     * @inheritdoc
     */
    public function getFieldLayout()
    {
        return Craft::$app->getFields()->getLayoutByType(self::class);
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
    protected function tableAttributeHtml(string $attribute): string
    {
        switch ($attribute) {
            case 'orderStatus':
            {
                if ($this->orderStatus) {
                    return $this->orderStatus->getLabelHtml();
                }
                return '<span class="status"></span>';
            }
            case 'shippingFullName':
            {
                if ($this->getShippingAddress()) {
                    return $this->getShippingAddress()->getFullName();
                }
                return '';
            }
            case 'billingFullName':
            {
                if ($this->getBillingAddress()) {
                    return $this->getBillingAddress()->getFullName();
                }
                return '';
            }
            case 'shippingBusinessName':
            {
                if ($this->getShippingAddress()) {
                    return $this->getShippingAddress()->businessName;
                }
                return '';
            }
            case 'billingBusinessName':
            {
                if ($this->getBillingAddress()) {
                    return $this->getBillingAddress()->businessName;
                }
                return '';
            }
            case 'shippingMethodName':
            {
                if ($this->getShippingMethod()) {
                    return $this->getShippingMethod()->name;
                }
                return '';
            }
            case 'gatewayName':
            {
                if ($this->getGateway()) {
                    return $this->getGateway()->name;
                }
                return '';
            }
            case 'paidStatus':
            {
                return $this->getPaidStatusHtml();
            }
            case 'totalPaid':
            {
                return Craft::$app->getFormatter()->asCurrency($this->getTotalPaid(), $this->currency);
            }
            case 'total':
            {
                return Craft::$app->getFormatter()->asCurrency($this->getTotal(), $this->currency);
            }
            case 'totalPrice':
            {
                return Craft::$app->getFormatter()->asCurrency($this->getTotalPrice(), $this->currency);
            }
            case 'totalShippingCost':
            {
                $amount = $this->getAdjustmentsTotalByType('shipping');
                return Craft::$app->getFormatter()->asCurrency($amount, $this->currency);
            }
            case 'totalDiscount':
            {
                $amount = $this->getAdjustmentsTotalByType('discount');
                if ($this->$attribute >= 0) {
                    return Craft::$app->getFormatter()->asCurrency($amount, $this->currency);
                }

                return Craft::$app->getFormatter()->asCurrency($amount * -1, $this->currency);
            }
            case 'totalTax':
            {
                $amount = $this->getAdjustmentsTotalByType('tax');
                return Craft::$app->getFormatter()->asCurrency($amount, $this->currency);
            }
            case 'totalIncludedTax':
            {
                $amount = $this->getAdjustmentsTotalByType('tax', true);
                return Craft::$app->getFormatter()->asCurrency($amount, $this->currency);
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
            'billingFirstName',
            'billingLastName',
            'billingFullName',
            'email',
            'number',
            'shippingFirstName',
            'shippingLastName',
            'shippingFullName',
            'shortNumber',
            'transactionReference',
            'username',
            'reference'
        ];
    }

    /**
     * @inheritdoc
     */
    public function getSearchKeywords(string $attribute): string
    {
        switch ($attribute) {
            case 'billingFirstName':
                return $this->billingAddress->firstName ?? '';
            case 'billingLastName':
                return $this->billingAddress->lastName ?? '';
            case 'billingFullName':
                return ($this->billingAddress->firstName ?? '') . ($this->billingAddress->lastName ?? '');
            case 'shippingFirstName':
                return $this->shippingAddress->firstName ?? '';
            case 'shippingLastName':
                return $this->shippingAddress->lastName ?? '';
            case 'shippingFullName':
                return ($this->shippingAddress->firstName ?? '') . ($this->shippingAddress->lastName ?? '');
            case 'transactionReference':
                return implode(' ', ArrayHelper::getColumn($this->getTransactions(), 'reference'));
            case 'username':
                return $this->getUser()->username ?? '';
            default:
                return parent::getSearchKeywords($attribute);
        }
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected static function defineSources(string $context = null): array
    {
        $allCriteria = ['isCompleted' => true];
        $count = Craft::configure(self::find(), $allCriteria)->count();

        $sources = [
            '*' => [
                'key' => '*',
                'label' => Craft::t('commerce', 'All Orders'),
                'criteria' => ['isCompleted' => true],
                'defaultSort' => ['dateOrdered', 'desc'],
                'badgeCount' => $count
            ]
        ];

        $sources[] = ['heading' => Craft::t('commerce', 'Order Status')];

        foreach (Plugin::getInstance()->getOrderStatuses()->getAllOrderStatuses() as $orderStatus) {
            $key = 'orderStatus:' . $orderStatus->handle;
            $criteriaStatus = ['orderStatusId' => $orderStatus->id];

            $count = (new Query())
                ->where(['o.orderStatusId' => $orderStatus->id, 'e.dateDeleted' => null])
                ->from(['{{%commerce_orders}} o'])
                ->leftJoin(['{{%elements}} e'], '[[o.id]] = [[e.id]]')
                ->count();

            $sources[] = [
                'key' => $key,
                'status' => $orderStatus->color,
                'label' => $orderStatus->name,
                'criteria' => $criteriaStatus,
                'defaultSort' => ['dateOrdered', 'desc'],
                'badgeCount' => $count
            ];
        }

        $sources[] = ['heading' => Craft::t('commerce', 'Carts')];

        $edge = new DateTime();
        $interval = new DateInterval('PT1H');
        $interval->invert = 1;
        $edge->add($interval);
        $edge = $edge->format(DateTime::ATOM);

        $updatedAfter = [];
        $updatedAfter[] = '>= ' . $edge;

        $criteriaActive = ['dateUpdated' => $updatedAfter, 'isCompleted' => 'not 1'];
        $sources[] = [
            'key' => 'carts:active',
            'label' => Craft::t('commerce', 'Active Carts'),
            'criteria' => $criteriaActive,
            'defaultSort' => ['commerce_orders.dateUpdated', 'asc'],
        ];
        $updatedBefore = [];
        $updatedBefore[] = '< ' . $edge;

        $criteriaInactive = ['dateUpdated' => $updatedBefore, 'isCompleted' => 'not 1'];
        $sources[] = [
            'key' => 'carts:inactive',
            'label' => Craft::t('commerce', 'Inactive Carts'),
            'criteria' => $criteriaInactive,
            'defaultSort' => ['commerce_orders.dateUpdated', 'desc']
        ];

        $criteriaAttemptedPayment = ['hasTransactions' => true, 'isCompleted' => 'not 1'];
        $sources[] = [
            'key' => 'carts:attempted-payment',
            'label' => Craft::t('commerce', 'Attempted Payments'),
            'criteria' => $criteriaAttemptedPayment,
            'defaultSort' => ['commerce_orders.dateUpdated', 'desc'],
        ];

        return $sources;
    }

    /**
     * @inheritdoc
     */
    protected static function defineActions(string $source = null): array
    {
        $actions = parent::defineActions($source);

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

            // Restore
            $actions[] = Craft::$app->getElements()->createAction([
                'type' => Restore::class,
                'successMessage' => Craft::t('commerce', 'Orders restored.'),
                'partialSuccessMessage' => Craft::t('commerce', 'Some orders restored.'),
                'failMessage' => Craft::t('commerce', 'Orders not restored.'),
            ]);
        }

        return $actions;
    }

    /**
     * @inheritdoc
     */
    protected static function defineTableAttributes(): array
    {
        return [
            'order' => ['label' => Craft::t('commerce', 'Order')],
            'reference' => ['label' => Craft::t('commerce', 'Reference')],
            'shortNumber' => ['label' => Craft::t('commerce', 'Short Number')],
            'number' => ['label' => Craft::t('commerce', 'Number')],
            'id' => ['label' => Craft::t('commerce', 'ID')],
            'orderStatus' => ['label' => Craft::t('commerce', 'Status')],
            'total' => ['label' => Craft::t('commerce', 'Total')],
            'totalPrice' => ['label' => Craft::t('commerce', 'Total')],
            'totalPaid' => ['label' => Craft::t('commerce', 'Total Paid')],
            'totalDiscount' => ['label' => Craft::t('commerce', 'Total Discount')],
            'totalShippingCost' => ['label' => Craft::t('commerce', 'Total Shipping')],
            'totalTax' => ['label' => Craft::t('commerce', 'Total Tax')],
            'totalIncludedTax' => ['label' => Craft::t('commerce', 'Total Included Tax')],
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
            'gatewayName' => ['label' => Craft::t('commerce', 'Gateway')],
            'paidStatus' => ['label' => Craft::t('commerce', 'Paid Status')]
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function defineDefaultTableAttributes(string $source = null): array
    {
        $attributes = [];
        $attributes[] = 'order';

        if (0 !== strpos($source, 'carts:')) {
            $attributes[] = 'reference';
            $attributes[] = 'orderStatus';
            $attributes[] = 'totalPrice';
            $attributes[] = 'dateOrdered';
            $attributes[] = 'totalPaid';
            $attributes[] = 'datePaid';
            $attributes[] = 'paidStatus';
        } else {
            $attributes[] = 'shortNumber';
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
            'reference' => Craft::t('commerce', 'Reference'),
            'id' => Craft::t('commerce', 'ID'),
            'orderStatusId' => Craft::t('commerce', 'Order Status'),
            'totalPrice' => Craft::t('commerce', 'Total Payable'),
            'totalPaid' => Craft::t('commerce', 'Total Paid'),
            'dateOrdered' => Craft::t('commerce', 'Date Ordered'),
            [
                'label' => Craft::t('commerce', 'Date Updated'),
                'orderBy' => 'commerce_orders.dateUpdated',
                'attribute' => 'dateUpdated'
            ],
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
    private function _saveAdjustments()
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

        return null;
    }

    /**
     * Updates the line items, including deleting the old ones.
     */
    private function _saveLineItems()
    {
        // Line items that are currently in the DB
        $previousLineItems = LineItemRecord::find()
            ->where(['orderId' => $this->id])
            ->all();

        $newLineItemIds = [];

        // Determine the line items that will be saved
        foreach ($this->getLineItems() as $lineItem) {
            // If the ID is null that's ok, it's a new line item and will be saves anyway
            $newLineItemIds[] = $lineItem->id;
        }

        // Delete any line items that no longer will be saved on this order.
        foreach ($previousLineItems as $previousLineItem) {
            if (!in_array($previousLineItem->id, $newLineItemIds, false)) {
                $previousLineItem->delete();
            }
        }

        // Save the line items last, as we know that any possible duplicates are already removed.
        // We also need to re-save any adjustments that didn't have an line item ID for a line item if it's new.
        foreach ($this->getLineItems() as $lineItem) {
            $lineItem->setOrder($this);
            // Don't run validation as validation of the line item should happen before saving the order
            Plugin::getInstance()->getLineItems()->saveLineItem($lineItem, false);

            // Update any adjustments to this line item with the new line item ID.
            foreach ($this->getAdjustments() as $adjustment) {
                // Was the adjustment for this line item, but the line item ID didn't exist when the adjustment was made?
                if ($adjustment->getLineItem() === $lineItem && !$adjustment->lineItemId) {
                    // Re-save the adjustment with the new line item ID, since it exists now.
                    $adjustment->lineItemId = $lineItem->id;
                    // Validation not needed as the adjustments are validated before the order is saved
                    Plugin::getInstance()->getOrderAdjustments()->saveOrderAdjustment($adjustment, false);
                }
            }
        }
    }
}
