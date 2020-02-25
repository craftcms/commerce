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
use craft\commerce\base\ShippingMethodInterface;
use craft\commerce\elements\traits\OrderDeprecatedTrait;
use craft\commerce\elements\traits\OrderElementTrait;
use craft\commerce\elements\traits\OrderValidatorsTrait;
use craft\commerce\errors\CurrencyException;
use craft\commerce\db\Table;
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
use craft\elements\User;
use craft\errors\ElementNotFoundException;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use craft\helpers\Template;
use craft\helpers\UrlHelper;
use craft\i18n\Locale;
use craft\web\View;
use DateTime;
use Throwable;
use yii\base\Exception;
use yii\base\InvalidArgumentException;
use yii\base\InvalidCallException;
use yii\base\InvalidConfigException;
use yii\behaviors\AttributeTypecastBehavior;
use yii\db\StaleObjectException;
use yii\log\Logger;

/**
 * Order or Cart model.
 *
 * @property OrderAdjustment[] $adjustments
 * @property string $email the email for this order
 * @property LineItem[] $lineItems
 * @property Address $billingAddress
 * @property Address $shippingAddress
 * @property PaymentSource|null $paymentSource
 * @property string $paymentCurrency the payment currency for this order
 * @property string $recalculationMode the mode of recalculation.
 * @property string $origin
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
 * @property-read string $orderStatusHtml
 * @property-read string $customerLinkHtml
 * @property null|array|Address $estimatedBillingAddress
 * @property float $totalDiscount
 * @property null|array|Address $estimatedShippingAddress
 * @property float $totalTaxIncluded
 * @property float $totalTax
 * @property float $totalShippingCost
 * @property-read Transaction[] $transactions
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Order extends Element
{
    use OrderValidatorsTrait;
    use OrderDeprecatedTrait;
    use OrderElementTrait;


    const PAID_STATUS_OVERPAID = 'overPaid';
    const PAID_STATUS_PAID = 'paid';
    const PAID_STATUS_PARTIAL = 'partial';
    const PAID_STATUS_UNPAID = 'unpaid';

    const RECALCULATION_MODE_ALL = 'all'; // Recalculates line item sales, populates from purchasables, and regenerates adjustments
    const RECALCULATION_MODE_NONE = 'none'; // Does not recalc sales, or populate from purchasable, or regenerate adjustments
    const RECALCULATION_MODE_ADJUSTMENTS_ONLY = 'adjustmentsOnly'; // Does not recalc sales, or populate from purchasable, and only regenerate adjustments

    const ORIGIN_WEB = 'web'; // Did the order get created from the front-end
    const ORIGIN_CP = 'cp'; // Did the order get created from the control panel
    const ORIGIN_REMOTE = 'remote'; // Was the order created by a remote API

    /**
     * @event \yii\base\Event The event that is triggered before a new line item has been added to the order.
     *
     * ```php
     * use craft\commerce\elements\Order;
     * use craft\commerce\models\LineItem;
     * use craft\commerce\events\LineItemEvent;
     * use yii\base\Event;
     *
     * Event::on(
     *     Order::class,
     *     Order::EVENT_BEFORE_ADD_LINE_ITEM,
     *     function(LineItemEvent $event) {
     *         // @var LineItem $lineItem
     *         $lineItem = $event->lineItem;
     *         // @var bool $isNew
     *         $isNew = $event->isNew;
     *         // @var bool $isValid
     *         $isValid = $event->isValid;
     *         // ...
     *     }
     * );
     * ```
     */
    const EVENT_BEFORE_ADD_LINE_ITEM = 'beforeAddLineItemToOrder';

    /**
     * @event \yii\base\Event The event that is triggered after a line item has been added to an order.
     *
     * ```php
     * use craft\commerce\elements\Order;
     * use craft\commerce\events\LineItemEvent;
     * use craft\commerce\models\LineItem;
     * use yii\base\Event;
     * 
     * Event::on(
     *     Order::class,
     *     Order::EVENT_AFTER_ADD_LINE_ITEM,
     *     function(LineItemEvent $event) {
     *         // @var LineItem $lineItem
     *         $lineItem = $event->lineItem;
     *         // @var bool $isNew
     *         $isNew = $event->isNew;
     *         // ...
     *     }
     * );
     */
    const EVENT_AFTER_ADD_LINE_ITEM = 'afterAddLineItemToOrder';

    /**
     * @event \yii\base\Event The event that is triggered after a line item has been removed from an order.
     * @todo Change to `afterRemoveLineItemFromOrder` in next major release (`To` → `From`)
     *
     * ```php
     * use craft\commerce\elements\Order;
     * use craft\commerce\events\LineItemEvent;
     * use craft\commerce\models\LineItem;
     * use yii\base\Event;
     *
     * Event::on(
     *     Order::class,
     *     Order::EVENT_AFTER_REMOVE_LINE_ITEM,
     *     function(LineItemEvent $event) {
     *         // @var LineItem $lineItem
     *         $lineItem = $event->lineItem;
     *         // @var bool $isNew
     *         $isNew = $event->isNew;
     *         // ...
     *     }
     * );
     * ```
     */
    const EVENT_AFTER_REMOVE_LINE_ITEM = 'afterRemoveLineItemToOrder';

    /**
     * @event \yii\base\Event The event that is triggered before an order is completed.
     *
     * ```php
     * use craft\commerce\elements\Order;
     * use yii\base\Event;
     *
     * Event::on(
     *     Order::class,
     *     Order::EVENT_BEFORE_COMPLETE_ORDER,
     *     function(Event $event) {
     *         // @var Order $order
     *         $order = $event->sender;
     *         // ...
     *     }
     * );
     * ```
     */
    const EVENT_BEFORE_COMPLETE_ORDER = 'beforeCompleteOrder';

    /**
     * @event \yii\base\Event The event that is triggered after an order is completed.
     *
     * ```php
     * use craft\commerce\elements\Order;
     * use yii\base\Event;
     *
     * Event::on(
     *     Order::class,
     *     Order::EVENT_AFTER_COMPLETE_ORDER,
     *     function(Event $event) {
     *         // @var Order $order
     *         $order = $event->sender;
     *         // ...
     *     }
     * );
     * ```
     */
    const EVENT_AFTER_COMPLETE_ORDER = 'afterCompleteOrder';

    /**
     * @event \yii\base\Event The event that is triggered after an order is paid and completed.
     *
     * ```php
     * use craft\commerce\elements\Order;
     * use yii\base\Event;
     * 
     * Event::on(
     *     Order::class,
     *     Order::EVENT_AFTER_ORDER_PAID,
     *     function(Event $event) {
     *         // @var Order $order
     *         $order = $event->sender;
     *         // ...
     *     }
     * );
     * ```
     */
    const EVENT_AFTER_ORDER_PAID = 'afterOrderPaid';

    /**
     * @event \yii\base\Event This event is raised after an order is authorized in full and completed
     *
     * Plugins can get notified after an order is authorized in full and completed
     *
     * ```php
     * use craft\commerce\elements\Order;
     * use yii\base\Event;
     *
     * Event::on(Order::class, Order::EVENT_AFTER_ORDER_AUTHORIZED, function(Event $e) {
     *     // @var Order $order
     *     $order = $e->sender;
     *     // ...
     * });
     * ```
     */
    const EVENT_AFTER_ORDER_AUTHORIZED = 'afterOrderAuthorized';

    /**
     * This is the unique number (hash) generated for the order when it was first created.
     *
     * @var string Number
     * ---
     * ```php
     * echo $order->number;
     * ```
     * ```twig
     * {{ order.number }}
     * ```
     */
    public $number;

    /**
     * This is the the reference number generated once the order was completed.
     * While the order is a cart, this is null.
     *
     * @var string Reference
     * ---
     * ```php
     * echo $order->reference;
     * ```
     * ```twig
     * {{ order.reference }}
     * ```
     */
    public $reference;

    /**
     * This is the currently applied coupon code.
     *
     * @var string|null Coupon Code
     * ---
     * ```php
     * echo $order->couponCode;
     * ```
     * ```twig
     * {{ order.couponCode }}
     * ```
     */
    public $couponCode;

    /**
     * Is this order completed (no longer a cart).
     *
     * @var bool Is completed
     * ---
     * ```php
     * echo $order->isCompleted;
     * ```
     * ```twig
     * {{ order.isCompleted }}
     * ```
     */
    public $isCompleted = false;

    /**
     * The date and time this order was completed
     *
     * @var DateTime Date ordered
     * ---
     * ```php
     * echo $order->dateOrdered;
     * ```
     * ```twig
     * {{ order.dateOrdered }}
     * ```
     */
    public $dateOrdered;

    /**
     * The date and time this order was paid in full.
     *
     * @var DateTime Date paid
     * ---
     * ```php
     * echo $order->datePaid;
     * ```
     * ```twig
     * {{ order.datePaid }}
     * ```
     */
    public $datePaid;

    /**
     * The date and time this order was authorized in full.
     * This may the same date as datePaid if the order was paid immediately.
     *
     * @var DateTime Date authorized
     * ---
     * ```php
     * echo $order->dateAuthorized;
     * ```
     * ```twig
     * {{ order.dateAuthorized }}
     * ```
     */
    public $dateAuthorized;

    /**
     * The currency of the order (ISO code)
     *
     * @var string Currency
     * ---
     * ```php
     * echo $order->currency;
     * ```
     * ```twig
     * {{ order.currency }}
     * ```
     */
    public $currency;

    /**
     * The current gateway ID to identify the gateway the order should use when accepting payments.
     * If the `paymentSourceId` is set on this order, this `gatewayId` will be that belonging to the
     * payment source.
     *
     * @var int|null Gateway ID
     * ---
     * ```php
     * echo $order->gatewayId;
     * ```
     * ```twig
     * {{ order.gatewayId }}
     * ```
     */
    public $gatewayId;

    /**
     * The last IP address of the user building the order before it was marked as complete.
     *
     * @var string|null Last IP address
     * ---
     * ```php
     * echo $order->lastIp;
     * ```
     * ```twig
     * {{ order.lastIp }}
     * ```
     */
    public $lastIp;

    /**
     * The current message set on the order when having it’s order status being changed.
     *
     * @var string|null message
     * ---
     * ```php
     * echo $order->message;
     * ```
     * ```twig
     * {{ order.message }}
     * ```
     */
    public $message;

    /**
     * The current URL the order should return to after successful payment.
     * This is stored on the order as we may be redirected off-site for payments.
     *
     * @var string Return URL
     * ---
     * ```php
     * echo $order->returnUrl;
     * ```
     * ```twig
     * {{ order.returnUrl }}
     * ```
     */
    public $returnUrl;

    /**
     * The current URL the order should return to if the customer cancels payment off-site.
     * This is stored on the order as we may be redirected off-site for payments.
     *
     * @var string Cancel URL
     * ---
     * ```php
     * echo $order->cancelUrl;
     * ```
     * ```twig
     * {{ order.cancelUrl }}
     * ```
     */
    public $cancelUrl;

    /**
     * The current order status ID. This will be null if the order is not complete
     * and is still a cart.
     *
     * @var int|null Order status ID
     * ---
     * ```php
     * echo $order->orderStatusId;
     * ```
     * ```twig
     * {{ order.orderStatusId }}
     * ```
     */
    public $orderStatusId;

    /**
     * The current order status ID. This will be null if the order is not complete
     * and is still a cart.
     *
     * @var int|null Order status ID
     * ---
     * ```php
     * echo $order->orderStatusId;
     * ```
     * ```twig
     * {{ order.orderStatusId }}
     * ```
     */
    public $orderLanguage;


    /**
     * The origin of the order when it was first created.
     * Values can be 'web', 'cp', or 'api'
     *
     * @var string Order origin
     * ---
     * ```php
     * echo $order->origin;
     * ```
     * ```twig
     * {{ order.origin }}
     * ```
     */
    public $origin;

    /**
     * The current billing address ID
     *
     * @var int|null Billing address ID
     * ---
     * ```php
     * echo $order->billingAddressId;
     * ```
     * ```twig
     * {{ order.billingAddressId }}
     * ```
     */
    public $billingAddressId;

    /**
     * The current shipping address ID
     *
     * @var int|null Shipping address ID
     * ---
     * ```php
     * echo $order->shippingAddressId;
     * ```
     * ```twig
     * {{ order.shippingAddressId }}
     * ```
     */
    public $shippingAddressId;


    /**
     * Whether or not the shipping address should be made the primary address of the
     * order‘s customer. This is not persisted on the order, and is only used during the
     * update order request.
     *
     * @var bool Make this the customer‘s primary shipping address
     * ---
     * ```php
     * echo $order->makePrimaryShippingAddress;
     * ```
     * ```twig
     * {{ order.makePrimaryShippingAddress }}
     * ```
     */
    public $makePrimaryShippingAddress;

    /**
     * Whether or not the billing address should be made the primary address of the
     * order‘s customer. This is not persisted on the order, and is only used during the
     * update order request.
     *
     * @var bool Make this the customer‘s primary billing address
     * ---
     * ```php
     * echo $order->makePrimaryBillingAddress;
     * ```
     * ```twig
     * {{ order.makePrimaryBillingAddress }}
     * ```
     */
    public $makePrimaryBillingAddress;

    /**
     * Whether or not the shipping address should be the same address as the order’s
     * billing address. This is not persisted on the order, and is only used during the
     * update order request. Can not be set to `true` at the same time as setting
     * `billingSameAsShipping` to true, or an error will be raised.
     *
     * @var bool Make this the shipping address the same as the billing address
     * ---
     * ```php
     * echo $order->shippingSameAsBilling;
     * ```
     * ```twig
     * {{ order.shippingSameAsBilling }}
     * ```
     */
    public $shippingSameAsBilling;

    /**
     * Whether or not the billing address should be the same address as the order’s
     * shipping address. This is not persisted on the order, and is only used during the
     * update order request. Can not be set to `true` at the same time as setting
     * `shippingSameAsBilling` to true, or an error will be raised.
     *
     * @var bool Make this the shipping address the same as the billing address
     * ---
     * ```php
     * echo $order->billingSameAsShipping;
     * ```
     * ```twig
     * {{ order.billingSameAsShipping }}
     * ```
     */
    public $billingSameAsShipping;

    /**
     * @var int Estimated Billing address ID
     * @since 2.2
     */
    public $estimatedBillingAddressId;

    /**
     * @var int Estimated Shipping address ID
     * @since 2.2
     */
    public $estimatedShippingAddressId;

    /**
     * @var bool Whether estimated billing address should be set to the same address as estimated shipping
     * @since 2.2
     */
    public $estimatedBillingSameAsShipping;

    /**
     * @var string Shipping Method Handle
     */
    public $shippingMethodHandle;

    /**
     * @var int Customer ID
     */
    public $customerId;

    /**
     * Whether the the email address on the order should be used to register
     * as a user account when the order is complete.
     *
     * @var bool Register user on order complete
     * ---
     * ```php
     * echo $order->registerUserOnOrderComplete;
     * ```
     * ```twig
     * {{ order.registerUserOnOrderComplete }}
     * ```
     */
    public $registerUserOnOrderComplete;

    /**
     * The current payment source that should be used to make payments on the
     * order. If this is set, the `gatewayId` will also be set to the related
     * gateway.
     *
     * @var bool Payment source ID
     * ---
     * ```php
     * echo $order->paymentSourceId;
     * ```
     * ```twig
     * {{ order.paymentSourceId }}
     * ```
     */
    public $paymentSourceId;


    /**
     * @var float The total price as stored in the database from last retrieval
     * ---
     * ```php
     * echo $order->storedTotalPrice;
     * ```
     * ```twig
     * {{ order.storedTotalPrice }}
     * ```
     */
    public $storedTotalPrice;

    /**
     * @var float The total paid as stored in the database from last retrieval
     * ---
     * ```php
     * echo $order->storedTotalPaid;
     * ```
     * ```twig
     * {{ order.storedTotalPaid }}
     * ```
     */
    public $storedTotalPaid;

    /**
     * @var float The item total as stored in the database from last retrieval
     * ---
     * ```php
     * echo $order->storedItemTotal;
     * ```
     * ```twig
     * {{ order.storedItemTotal }}
     * ```
     */
    public $storedItemTotal;

    /**
     * @var float The total shipping cost adjustments as stored in the database from last retrieval
     * ---
     * ```php
     * echo $order->storedTotalShippingCost;
     * ```
     * ```twig
     * {{ order.storedTotalShippingCost }}
     * ```
     */
    public $storedTotalShippingCost;

    /**
     * @var float The total of discount adjustments as stored in the database from last retrieval
     * ---
     * ```php
     * echo $order->storedTotalDiscount;
     * ```
     * ```twig
     * {{ order.storedTotalDiscount }}
     * ```
     */
    public $storedTotalDiscount;

    /**
     * @var float The total tax adjustments as stored in the database from last retrieval
     * ---
     * ```php
     * echo $order->storedTotalTax;
     * ```
     * ```twig
     * {{ order.storedTotalTax }}
     * ```
     */
    public $storedTotalTax;

    /**
     * @var float The total tax included  adjustments as stored in the database from last retrieval
     * ---
     * ```php
     * echo $order->storedTotalTaxIncluded;
     * ```
     * ```twig
     * {{ order.storedTotalTaxIncluded }}
     * ```
     */
    public $storedTotalTaxIncluded;


    /**
     * @var string
     * @see Order::setRecalculationMode() To set the current recalculation mode
     * @see Order::getRecalculationMode() To get the current recalculation mode
     * ---
     * ```php
     * echo $order->recalculationMode;
     * ```
     * ```twig
     * {{ order.recalculationMode }}
     * ```
     */
    private $_recalculationMode;

    /**
     * @var Address|null
     * @see Order::setShippingAddress() To set the current shipping address
     * @see Order::getShippingAddress() To get the current shipping address
     * ---
     * ```php
     * if ($order->shippingAddress) {
     * echo $order->shippingAddress->firstName;
     * }
     * ```
     * ```twig
     * {% if order.shippingAddress %}
     * {{ order.shippingAddress.firstName }}
     * {% endif %}
     * ```
     */
    private $_shippingAddress;

    /**
     * @var Address|null
     * @see Order::setBillingAddress() To set the current billing address
     * @see Order::getBillingAddress() To get the current billing address
     * ---
     * ```php
     * if ($order->billingAddress) {
     * echo $order->billingAddress->firstName;
     * }
     * ```
     * ```twig
     * {% if order.billingAddress %}
     * {{ order.billingAddress.firstName }}
     * {% endif %}
     * ```
     */
    private $_billingAddress;

    /**
     * @var Address
     * @since 2.2
     */
    private $_estimatedShippingAddress;

    /**
     * @var Address
     * @since 2.2
     */
    private $_estimatedBillingAddress;

    /**
     * @var LineItem[]
     * @see Order::setLineItems() To set the order line items
     * @see Order::getLineItems() To get the order line items
     * ---
     * ```php
     * foreach ($order->getLineItems() as $lineItem) {
     * echo $lineItem->description';
     * }
     * ```
     * ```twig
     * {% for lineItem in order.lineItems %}
     * {{ lineItem.description }}
     * {% endif %}
     * ```
     */
    private $_lineItems;

    /**
     * @var OrderAdjustment[]
     * @see Order::setAdjustments() To set the order adjustments
     * @see Order::setAdjustments() To get the order adjustments
     * ---
     * ```php
     * foreach ($order->getAdjustments() as $adjustment) {
     * echo $adjustment->amount';
     * }
     * ```
     * ```twig
     * {% for adjustment in order.adjustments %}
     * {{ adjustment.amount }}
     * {% endif %}
     * ```
     */
    private $_orderAdjustments;

    /**
     * @var string
     * @see Order::setPaymentCurrency() To set the payment currency
     * @see Order::getPaymentCurrency() To get the payment currency
     * ---
     * ```php
     * echo $order->paymentCurrency;
     * ```
     * ```twig
     * {{ order.paymentCurrency }}
     * ```
     */
    private $_paymentCurrency;

    /**
     * @var string
     * @see Order::setEmail() To set the order email
     * @see Order::getEmail() To get the email
     * ---
     * ```php
     * echo $order->email;
     * ```
     * ```twig
     * {{ order.email }}
     * ```
     */
    private $_email;


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

        if ($this->orderLanguage === null) {
            $this->orderLanguage = Craft::$app->language;
        }

        if ($this->currency === null) {
            $this->currency = Plugin::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrencyIso();
        }

        // Better default for carts if the base currency changes (usually only happens in development)
        if (!$this->isCompleted && $this->paymentCurrency && !Plugin::getInstance()->getPaymentCurrencies()->getPaymentCurrencyByIso($this->paymentCurrency)) {
            $this->paymentCurrency = Plugin::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrencyIso();
        }

        if ($this->origin === null) {
            $this->origin = static::ORIGIN_WEB;
        }

        if ($this->_recalculationMode === null) {
            if ($this->isCompleted) {
                $this->setRecalculationMode(self::RECALCULATION_MODE_NONE);
            } else {
                $this->setRecalculationMode(self::RECALCULATION_MODE_ALL);
            }
        }

        return parent::init();
    }

    public function behaviors(): array
    {
        $behaviors = parent::behaviors();

        $behaviors['typecast'] = [
            'class' => AttributeTypecastBehavior::className(),
            'attributeTypes' => [
                'id' => AttributeTypecastBehavior::TYPE_INTEGER,
                'number' => AttributeTypecastBehavior::TYPE_STRING,
                'reference' => AttributeTypecastBehavior::TYPE_STRING,
                'couponCode' => AttributeTypecastBehavior::TYPE_STRING,
                'isCompleted' => AttributeTypecastBehavior::TYPE_BOOLEAN,
                'gatewayId' => AttributeTypecastBehavior::TYPE_INTEGER,
                'lastIp' => AttributeTypecastBehavior::TYPE_STRING,
                'orderLanguage' => AttributeTypecastBehavior::TYPE_STRING,
                'message' => AttributeTypecastBehavior::TYPE_STRING,
                'returnUrl' => AttributeTypecastBehavior::TYPE_STRING,
                'cancelUrl' => AttributeTypecastBehavior::TYPE_STRING,
                'orderStatusId' => AttributeTypecastBehavior::TYPE_INTEGER,
                'origin' => AttributeTypecastBehavior::TYPE_STRING,
                'billingAddressId' => AttributeTypecastBehavior::TYPE_INTEGER,
                'shippingAddressId' => AttributeTypecastBehavior::TYPE_INTEGER,
                'makePrimaryShippingAddress' => AttributeTypecastBehavior::TYPE_BOOLEAN,
                'makePrimaryBillingAddress' => AttributeTypecastBehavior::TYPE_BOOLEAN,
                'shippingSameAsBilling' => AttributeTypecastBehavior::TYPE_BOOLEAN,
                'billingSameAsShipping' => AttributeTypecastBehavior::TYPE_BOOLEAN,
                'shippingMethodHandle' => AttributeTypecastBehavior::TYPE_STRING,
                'customerId' => AttributeTypecastBehavior::TYPE_INTEGER,
            ]
        ];

        return $behaviors;
    }

    /**
     * @return null|string
     */
    public static function displayName(): string
    {
        return Plugin::t('Order');
    }

    /**
     * @inheritdoc
     */
    public static function lowerDisplayName(): string
    {
        return Plugin::t('order');
    }

    /**
     * @inheritdoc
     */
    public static function pluralDisplayName(): string
    {
        return Plugin::t('Orders');
    }

    /**
     * @inheritdoc
     */
    public static function pluralLowerDisplayName(): string
    {
        return Plugin::t('orders');
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
    public function beforeValidate(): bool
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
        if ($customer && $email = $customer->getEmail()) {
            $this->setEmail($email);
        }

        return parent::beforeValidate();
    }

    /**
     * @inheritdoc
     */
    public function datetimeAttributes(): array
    {
        $commerce = Craft::$app->getPlugins()->getStoredPluginInfo('commerce');

        $attributes = parent::datetimeAttributes();
        $attributes[] = 'datePaid';
        if ($commerce && version_compare($commerce['version'], '3.0.6', '>=')) {
            $attributes[] = 'dateAuthorized';
        }
        $attributes[] = 'dateOrdered';
        $attributes[] = 'dateUpdated';
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
        $names[] = 'paymentCurrency';
        $names[] = 'email';
        $names[] = 'isPaid';
        $names[] = 'itemSubtotal';
        $names[] = 'itemTotal';
        $names[] = 'lineItems';
        $names[] = 'orderAdjustments';
        $names[] = 'outstandingBalance';
        $names[] = 'paidStatus';
        $names[] = 'recalculationMode';
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
     * The attributes on the order that should be made available as formatted currency.
     *
     * @return array
     */
    public function currencyAttributes(): array
    {
        $attributes = [];
        $attributes[] = 'adjustmentSubtotal';
        $attributes[] = 'adjustmentsTotal';
        $attributes[] = 'itemSubtotal';
        $attributes[] = 'itemTotal';
        $attributes[] = 'outstandingBalance';
        $attributes[] = 'totalPaid';
        $attributes[] = 'total';
        $attributes[] = 'totalPrice';
        $attributes[] = 'totalSaleAmount';
        $attributes[] = 'totalTaxablePrice';
        $attributes[] = 'totalTax';
        $attributes[] = 'totalTaxIncluded';
        $attributes[] = 'totalShippingCost';
        $attributes[] = 'totalDiscount';
        return $attributes;
    }

    /**
     * @return array
     */
    public function fields(): array
    {
        $fields = parent::fields();

        foreach ($this->currencyAttributes() as $attribute) {
            $fields[$attribute . 'AsCurrency'] = function($model, $attribute) {
                // Substr because attribute is returned with 'AsCurrency' appended
                $attribute = substr($attribute, 0, -10);
                $amount = $model->$attribute ?? 0;
                return Craft::$app->getFormatter()->asCurrency($amount, $this->currency, [], [], true);
            };
        }

        foreach ($this->datetimeAttributes() as $attribute) {
            $fields[$attribute] = function($model, $attribute) {
                if (!empty($model->$attribute)) {
                    $formatter = Craft::$app->getFormatter();

                    return [
                        'date' => $formatter->asDate($model->$attribute, Locale::LENGTH_SHORT),
                        'time' => $formatter->asTime($model->$attribute, Locale::LENGTH_SHORT)
                    ];
                }

                return $model->$attribute;
            };
        }

        $fields['paidStatusHtml'] = 'paidStatusHtml';
        $fields['customerLinkHtml'] = 'customerLinkHtml';
        $fields['orderStatusHtml'] = 'orderStatusHtml';

        return $fields;
    }

    /**
     * @inheritdoc
     */
    public function extraFields(): array
    {
        $names = parent::extraFields();
        $names[] = 'availableShippingMethods';
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
        $names[] = 'transactions';
        $names[] = 'totalTax';
        $names[] = 'totalTaxIncluded';
        $names[] = 'totalShippingCost';
        $names[] = 'totalDiscount';
        return $names;
    }

    /**
     * @inheritdoc
     */
    public function defineRules(): array
    {
        $rules = parent::defineRules();

        // Address models are valid
        $rules[] = [
            ['billingAddress', 'shippingAddress'], 'validateAddress'
        ]; // from OrderValidatorTrait

        // Do addresses  belong to the customer of the order (only checked if the order is a cart)
        $rules[] = [
            ['billingAddress', 'shippingAddress'], 'validateAddressCanBeUsed'
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
     * Updates the paid status and paid date of the order, and marks as complete if the order is paid or authorized.
     */
    public function updateOrderPaidInformation()
    {
        $paidInFull = !$this->hasOutstandingBalance();
        $authorizedInFull = Plugin::getInstance()->getPayments()->getTotalAuthorizedOnlyForOrder($this) >= $this->getTotalPrice();

        $justPaid = $paidInFull && $this->datePaid == null;
        $justAuthorized = $authorizedInFull && $this->dateAuthorized == null;

        // If it is no longer paid in full, set datePaid to null
        if (!$paidInFull) {
            $this->datePaid = null;
        }

        // If it is no longer authorized in full, set dateAuthorized to null
        if (!$authorizedInFull) {
            $this->dateAuthorized = null;
        }

        // If it was just paid set the date paid to now.
        if ($justPaid) {
            $this->datePaid = Db::prepareDateForDb(new DateTime());
        }

        // If it was just authorized set the date authorized to now.
        if ($justAuthorized) {
            $this->dateAuthorized = Db::prepareDateForDb(new DateTime());
        }

        // Lock for recalculation
        $originalRecalculationMode = $this->getRecalculationMode();
        $this->setRecalculationMode(self::RECALCULATION_MODE_NONE);

        // Saving the order will update the datePaid as set above and also update the paidStatus.
        Craft::$app->getElements()->saveElement($this, false);

        // If the order is now paid or authorized in full, lets mark it as complete if it has not already been.
        if (!$this->isCompleted) {
            $totalAuthorized = Plugin::getInstance()->getPayments()->getTotalAuthorizedOnlyForOrder($this);
            if ($totalAuthorized >= $this->getTotalPrice() || $paidInFull) {
                // We need to remove the payment source from the order now that it's paid
                // This means the order needs new payment details for future payments: https://github.com/craftcms/commerce/issues/891
                // Payment information is still stored in the transactions.
                $this->paymentSourceId = null;

                $this->markAsComplete();
            }
        }

        if ($justPaid && $this->hasEventHandlers(self::EVENT_AFTER_ORDER_PAID)) {
            $this->trigger(self::EVENT_AFTER_ORDER_PAID);
        }

        if ($justAuthorized && $this->hasEventHandlers(self::EVENT_AFTER_ORDER_AUTHORIZED)) {
            $this->trigger(self::EVENT_AFTER_ORDER_AUTHORIZED);
        }

        // restore recalculation lock state
        $this->setRecalculationMode($originalRecalculationMode);
    }

    /**
     * Returns the total price of the order, minus any tax adjustments.
     *
     * @return float
     * @deprecated in 2.2.9. Use `craft\commerce\adjusters\Tax::_getOrderTotalTaxablePrice()` instead.
     */
    public function getTotalTaxablePrice(): float
    {
        $itemTotal = $this->getItemSubtotal();

        $allNonIncludedAdjustmentsTotal = $this->getAdjustmentsTotal();
        $taxAdjustments = $this->getTotalTax();
        $includedTaxAdjustments = $this->getTotalTaxIncluded();

        return $itemTotal + $allNonIncludedAdjustmentsTotal - ($taxAdjustments + $includedTaxAdjustments);
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
            ->from([Table::ORDERS])
            ->where(['isCompleted' => true])
            ->andWhere(['id' => $this->id])
            ->exists();

        if ($completedInDb) {
            $mutex->release($lockName);
            return true;
        }

        $this->isCompleted = true;
        $this->dateOrdered = Db::prepareDateForDb(new DateTime());

        // Reset estimated address relations
        $this->estimatedShippingAddressId = null;
        $this->estimatedBillingAddressId = null;

        $orderStatus = Plugin::getInstance()->getOrderStatuses()->getDefaultOrderStatusForOrder($this);

        // If the order status returned was overridden by a plugin, use the configured default order status if they give us a bogus one with no ID.
        if ($orderStatus && $orderStatus->id) {
            $this->orderStatusId = $orderStatus->id;
        } else {
            $mutex->release($lockName);
            throw new OrderStatusException('Could not find a valid default order status.');
        }

        if ($this->reference == null) {
            $referenceTemplate = Plugin::getInstance()->getSettings()->orderReferenceFormat;

            try {
                $this->reference = Craft::$app->getView()->renderObjectTemplate($referenceTemplate, $this);
            } catch (Throwable $exception) {
                $mutex->release($lockName);
                Craft::error('Unable to generate order completion reference for order ID: ' . $this->id . ', with format: ' . $referenceTemplate . ', error: ' . $exception->getMessage());
                throw $exception;
            }
        }

        // Raising the 'beforeCompleteOrder' event
        if ($this->hasEventHandlers(self::EVENT_BEFORE_COMPLETE_ORDER)) {
            $this->trigger(self::EVENT_BEFORE_COMPLETE_ORDER);
        }

        // Completed orders should no longer recalculate anything by default
        $this->setRecalculationMode(static::RECALCULATION_MODE_NONE);

        $success = Craft::$app->getElements()->saveElement($this, false);

        if (!$success) {
            Craft::error(Plugin::t('Could not mark order {number} as complete. Order save failed during order completion with errors: {order}',
                ['number' => $this->number, 'order' => json_encode($this->errors)]), __METHOD__);

            $mutex->release($lockName);
            return false;
        }

        $mutex->release($lockName);

        $this->afterOrderComplete();

        return true;
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
     * Gets the recalculation mode of the order
     *
     * @return string
     */
    public function getRecalculationMode(): string
    {
        return $this->_recalculationMode;
    }

    /**
     * Sets the recalculation mode of the order
     *
     * @param $value
     */
    public function setRecalculationMode($value)
    {
        $this->_recalculationMode = $value;
    }

    /**
     * Regenerates all adjusters and updates line items, depending on the current recalculationMode
     *
     * @throws Exception
     */
    public function recalculate()
    {
        if (!$this->id) {
            throw new InvalidCallException('Do not recalculate an order that has not been saved');
        }

        if ($this->hasErrors()) {
            Craft::getLogger()->log(Plugin::t('Do not call recalculate on the order (Number: {orderNumber}) if errors are present.', ['orderNumber' => $this->number]), Logger::LEVEL_INFO);
            return;
        }

        if ($this->getRecalculationMode() == self::RECALCULATION_MODE_NONE) {
            return;
        }

        if ($this->getRecalculationMode() == self::RECALCULATION_MODE_ALL) {
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
        }

        if ($this->getRecalculationMode() == self::RECALCULATION_MODE_ALL || $this->getRecalculationMode() == self::RECALCULATION_MODE_ADJUSTMENTS_ONLY) {
            //clear adjustments
            $this->setAdjustments([]);

            foreach (Plugin::getInstance()->getOrderAdjustments()->getAdjusters() as $adjuster) {
                /** @var AdjusterInterface $adjuster */
                $adjuster = new $adjuster();
                $adjustments = $adjuster->adjust($this);
                $this->setAdjustments(array_merge($this->getAdjustments(), $adjustments));
            }
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
     * @inheritdoc
     */
    public function afterSave(bool $isNew)
    {
        // Make sure addresses are set before recalculation so that on the next page load
        // the correct adjustments and totals are shown
        if ($this->shippingSameAsBilling) {
            $this->setShippingAddress($this->getBillingAddress());
        }

        if ($this->billingSameAsShipping) {
            $this->setBillingAddress($this->getShippingAddress());
        }

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
        $orderRecord->email = $this->getEmail() ?: '';
        $orderRecord->isCompleted = $this->isCompleted;
        $orderRecord->dateOrdered = $this->dateOrdered;
        $orderRecord->datePaid = $this->datePaid ?: null;
        $orderRecord->dateAuthorized = $this->dateAuthorized ?: null;
        $orderRecord->shippingMethodHandle = $this->shippingMethodHandle;
        $orderRecord->paymentSourceId = $this->getPaymentSource() ? $this->getPaymentSource()->id : null;
        $orderRecord->gatewayId = $this->gatewayId;
        $orderRecord->orderStatusId = $this->orderStatusId;
        $orderRecord->couponCode = $this->couponCode;
        $orderRecord->total = $this->getTotal();
        $orderRecord->totalPrice = $this->getTotalPrice();
        $orderRecord->totalPaid = $this->getTotalPaid();
        $orderRecord->totalDiscount = $this->getTotalDiscount();
        $orderRecord->totalShippingCost = $this->getTotalShippingCost();
        $orderRecord->totalTax = $this->getTotalTax();
        $orderRecord->totalTaxIncluded = $this->getTotalTaxIncluded();
        $orderRecord->currency = $this->currency;
        $orderRecord->lastIp = $this->lastIp;
        $orderRecord->orderLanguage = $this->orderLanguage;
        $orderRecord->origin = $this->origin;
        $orderRecord->paymentCurrency = $this->paymentCurrency;
        $orderRecord->customerId = $this->customerId;
        $orderRecord->registerUserOnOrderComplete = $this->registerUserOnOrderComplete;
        $orderRecord->returnUrl = $this->returnUrl;
        $orderRecord->cancelUrl = $this->cancelUrl;
        $orderRecord->message = $this->message;
        $orderRecord->paidStatus = $this->getPaidStatus();
        $orderRecord->recalculationMode = $this->getRecalculationMode();

        $customer = $this->getCustomer();
        $existingAddresses = $customer ? $customer->getAddresses() : [];

        $customerUser = $customer->getUser();
        $currentUser = Craft::$app->getUser()->getIdentity();
        $noCustomerUserOrCurrentUser = ($customerUser == null && $currentUser == null);
        $currentUserDoesntMatchCustomerUser = ($currentUser && ($customerUser == null || $currentUser->id != $customerUser->id));

        // Save shipping address, it has already been validated.
        if ($shippingAddress = $this->getShippingAddress()) {
            // We need to only save the address to the customers address book while it is a cart and not being edited by another user
            if ($customer && ($noCustomerUserOrCurrentUser || !$currentUserDoesntMatchCustomerUser) && !$this->isCompleted) {
                Plugin::getInstance()->getCustomers()->saveAddress($shippingAddress, $customer, false);
            } else {
                Plugin::getInstance()->getAddresses()->saveAddress($shippingAddress, false);
            }

            $orderRecord->shippingAddressId = $shippingAddress->id;
            $this->setShippingAddress($shippingAddress);
        }

        // Save billing address, it has already been validated.
        if ($billingAddress = $this->getBillingAddress()) {
            // We need to only save the address to the customers address book while it is a cart and not being edited by another user
            if ($customer && ($noCustomerUserOrCurrentUser || !$currentUserDoesntMatchCustomerUser) && !$this->isCompleted) {
                Plugin::getInstance()->getCustomers()->saveAddress($billingAddress, $customer, false);
            } else {
                Plugin::getInstance()->getAddresses()->saveAddress($billingAddress, false);
            }

            $orderRecord->billingAddressId = $billingAddress->id;
            $this->setBillingAddress($billingAddress);
        }

        if ($estimatedShippingAddress = $this->getEstimatedShippingAddress()) {
            Plugin::getInstance()->getAddresses()->saveAddress($estimatedShippingAddress, false);

            $orderRecord->estimatedShippingAddressId = $estimatedShippingAddress->id;
            $this->setEstimatedShippingAddress($estimatedShippingAddress);

            // If estimate billing same as shipping set it here
            if ($this->estimatedBillingSameAsShipping) {
                $orderRecord->estimatedBillingAddressId = $estimatedShippingAddress->id;
                $this->setEstimatedBillingAddress($estimatedShippingAddress);
            }
        }

        if (!$this->estimatedBillingSameAsShipping && $estimatedBillingAddress = $this->getEstimatedBillingAddress()) {
            Plugin::getInstance()->getAddresses()->saveAddress($estimatedBillingAddress, false);

            $orderRecord->estimatedBillingAddressId = $estimatedBillingAddress->id;
            $this->setEstimatedBillingAddress($estimatedBillingAddress);
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
     * @throws Exception
     */
    public function getPdfUrl($option = null)
    {
        $url = null;
        $view = Craft::$app->getView();
        $oldTemplateMode = $view->getTemplateMode();
        $view->setTemplateMode(View::TEMPLATE_MODE_SITE);
        $file = Plugin::getInstance()->getSettings()->orderPdfPath;

        if (!$file || !$view->doesTemplateExist($file)) {
            $view->setTemplateMode($oldTemplateMode);
            return null;
        }
        $view->setTemplateMode($oldTemplateMode);

        $path = "commerce/downloads/pdf?number={$this->number}" . ($option ? "&option={$option}" : '');
        $url = UrlHelper::actionUrl(trim($path, '/'));

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
     * @throws InvalidConfigException
     */
    public function getUser()
    {
        return $this->getCustomer() ? $this->getCustomer()->getUser() : null;
    }

    /**
     * Returns the email for this order. Will always be the registered users email if the order's customer is related to a user.
     *
     * @return string|null
     * @throws InvalidConfigException
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
     * @return bool
     */
    public function getIsUnpaid(): bool
    {
        return $this->hasOutstandingBalance();
    }

    /**
     * What is the status of the orders payment
     *
     * @return string
     */
    public function getPaidStatus(): string
    {
        if ($this->getIsPaid() && $this->getTotal() > 0 && $this->getTotalPaid() > $this->getTotal()) {
            return self::PAID_STATUS_OVERPAID;
        }

        if ($this->getIsPaid()) {
            return self::PAID_STATUS_PAID;
        }

        if ($this->totalPaid > 0) {
            return self::PAID_STATUS_PARTIAL;
        }

        return self::PAID_STATUS_UNPAID;
    }

    /**
     * Customer represented as HTML
     *
     * @return string
     * @since 3.0
     */
    public function getCustomerLinkHtml(): string
    {
        $currentUser = Craft::$app->getUser()->getIdentity();

        if (!$currentUser) {
            return '';
        }

        if ($this->getCustomer() && $this->isCompleted && $currentUser->can('commerce-manageCustomers')) {
            return '<span><a href="' . $this->getCustomer()->getCpEditUrl() . '">' . $this->email . '</a></span>';
        }

        if ($this->getCustomer() && $this->email && $currentUser->can('commerce-manageOrders')) {
            return '<span>' . $this->email . '</span>';
        }

        return '';
    }

    /**
     * @return string
     */
    public function getOrderStatusHtml(): string
    {
        if ($status = $this->getOrderStatus()) {
            return '<span class="commerceStatusLabel"><span class="status ' . $status->color . '"></span> ' . $status->name . '</span>';
        }

        return '';
    }

    /**
     * Paid status represented as HTML
     *
     * @return string
     */
    public function getPaidStatusHtml(): string
    {
        switch ($this->getPaidStatus()) {
            case self::PAID_STATUS_OVERPAID:
            {
                return '<span class="commerceStatusLabel"><span class="status blue"></span> ' . Plugin::t('Overpaid') . '</span>';
            }
            case self::PAID_STATUS_PAID:
            {
                return '<span class="commerceStatusLabel"><span class="status green"></span> ' . Plugin::t('Paid') . '</span>';
            }
            case self::PAID_STATUS_PARTIAL:
            {
                return '<span class="commerceStatusLabel"><span class="status orange"></span> ' . Plugin::t('Partial') . '</span>';
            }
            case self::PAID_STATUS_UNPAID:
            {
                return '<span class="commerceStatusLabel"><span class="status red"></span> ' . Plugin::t('Unpaid') . '</span>';
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
            return Currency::round(max($this->getTotalShippingCost(), $total));
        }

        return Currency::round($total);
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
    public function hasOutstandingBalance(): bool
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
     * Returns whether this order is the user's current active cart.
     *
     * @return bool
     * @throws ElementNotFoundException
     * @throws Exception
     * @throws Throwable
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
     * @return bool
     */
    public function hasLineItems(): bool
    {
        return (bool)$this->getLineItems();
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
     * @deprecated in 2.2
     */
    public function getAdjustmentsTotalByType($types, $included = false)
    {
        Craft::$app->getDeprecator()->log('Order::getAdjustmentsTotalByType()', 'Order::getAdjustmentsTotalByType() has been deprecated. Use Order::getTotalTax(), Order::getTotalDiscount(), Order::getTotalShippingCost() instead.');

        return $this->_getAdjustmentsTotalByType($types, $included);
    }

    /**
     * @param string|array $types
     * @param bool $included
     * @return float|int
     */
    public function _getAdjustmentsTotalByType($types, $included = false)
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
    public function getTotalTax(): float
    {
        return $this->_getAdjustmentsTotalByType('tax');
    }

    /**
     * @return float
     */
    public function getTotalTaxIncluded(): float
    {
        return $this->_getAdjustmentsTotalByType('tax', true);
    }

    /**
     * @return float
     */
    public function getTotalDiscount(): float
    {
        return $this->_getAdjustmentsTotalByType('discount');
    }

    /**
     * @return float
     */
    public function getTotalShippingCost(): float
    {
        return $this->_getAdjustmentsTotalByType('shipping');
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
     * @param string $type
     * @return array
     * @since 3.0
     */
    public function getAdjustmentsByType(string $type): array
    {
        $adjustments = [];

        foreach ($this->getAdjustments() as $adjustment) {
            if ($adjustment->type === $type) {
                $adjustments[] = $adjustment;
            }
        }

        return $adjustments;
    }

    /**
     * @return array
     */
    public function getOrderAdjustments(): array
    {
        $adjustments = $this->getAdjustments();
        $orderAdjustments = [];

        foreach ($adjustments as $adjustment) {
            if (!$adjustment->getLineItem() && $adjustment->orderId == $this->id) {
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
     * @since 2.2
     */
    public function getEstimatedShippingAddress()
    {
        if (null === $this->_estimatedShippingAddress && $this->estimatedShippingAddressId) {
            $this->_estimatedShippingAddress = Plugin::getInstance()->getAddresses()->getAddressById($this->estimatedShippingAddressId);
        }

        return $this->_estimatedShippingAddress;
    }

    /**
     * @param Address|array $address
     * @since 2.2
     */
    public function setEstimatedShippingAddress($address)
    {
        if (!$address instanceof Address) {
            $address = new Address($address);
        }
        $address->isEstimated = true;

        $this->estimatedShippingAddressId = $address->id;
        $this->_estimatedShippingAddress = $address;
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
     * @return Address|null
     * @since 2.2
     */
    public function getEstimatedBillingAddress()
    {
        if (null === $this->_estimatedBillingAddress && $this->estimatedBillingAddressId) {
            $this->_estimatedBillingAddress = Plugin::getInstance()->getAddresses()->getAddressById($this->estimatedBillingAddressId);
        }

        return $this->_estimatedBillingAddress;
    }

    /**
     * @param Address|array $address
     * @since 2.2
     */
    public function setEstimatedBillingAddress($address)
    {
        if (!$address instanceof Address) {
            $address = new Address($address);
        }
        $address->isEstimated = true;

        $this->estimatedBillingAddressId = $address->id;
        $this->_estimatedBillingAddress = $address;
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
     * @throws InvalidConfigException
     * @throws CurrencyException
     */
    public function getPaymentCurrency(): string
    {
        if ($this->_paymentCurrency === null) {
            $this->_paymentCurrency = Plugin::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrencyIso();
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
     * @param $value
     * @return string
     * @throws InvalidConfigException
     */
    private function _asCurrency($value)
    {
        $value = $value ?? 0;
        return Craft::$app->getFormatter()->asCurrency($value, $this->currency);
    }


    /**
     * Updates the adjustments, including deleting the old ones.
     *
     * @return null
     * @throws Exception
     * @throws Throwable
     * @throws StaleObjectException
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
            $adjustment->orderId = $this->id;
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

        $currentLineItemIds = [];

        // Determine the line items that will be saved
        foreach ($this->getLineItems() as $lineItem) {
            // If the ID is null that's ok, it's a new line item and will be saves anyway
            $currentLineItemIds[] = $lineItem->id;
        }

        // Delete any line items that no longer will be saved on this order.
        foreach ($previousLineItems as $previousLineItem) {
            if (!in_array($previousLineItem->id, $currentLineItemIds, false)) {
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
