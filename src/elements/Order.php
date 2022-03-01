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
use craft\commerce\behaviors\CurrencyAttributeBehavior;
use craft\commerce\behaviors\ValidateOrganizationTaxIdBehavior;
use craft\commerce\db\Table;
use craft\elements\Address as AddressElement;
use craft\commerce\elements\traits\OrderElementTrait;
use craft\commerce\elements\traits\OrderNoticesTrait;
use craft\commerce\elements\traits\OrderValidatorsTrait;
use craft\commerce\errors\CurrencyException;
use craft\commerce\errors\OrderStatusException;
use craft\commerce\events\AddLineItemEvent;
use craft\commerce\events\LineItemEvent;
use craft\commerce\helpers\Currency;
use craft\commerce\helpers\Order as OrderHelper;
use craft\commerce\models\Address;
use craft\commerce\models\LineItem;
use craft\commerce\models\OrderAdjustment;
use craft\commerce\models\OrderHistory;
use craft\commerce\models\OrderNotice;
use craft\commerce\models\OrderStatus;
use craft\commerce\models\PaymentSource;
use craft\commerce\models\Settings;
use craft\commerce\models\ShippingMethod;
use craft\commerce\models\ShippingMethodOption;
use craft\commerce\models\Transaction;
use craft\commerce\Plugin;
use craft\commerce\records\LineItem as LineItemRecord;
use craft\commerce\records\Order as OrderRecord;
use craft\commerce\records\OrderAdjustment as OrderAdjustmentRecord;
use craft\commerce\records\OrderNotice as OrderNoticeRecord;
use craft\commerce\records\Transaction as TransactionRecord;
use craft\db\Query;
use craft\elements\User;
use craft\errors\ElementNotFoundException;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;
use craft\helpers\Html;
use craft\helpers\StringHelper;
use craft\helpers\Template;
use craft\helpers\UrlHelper;
use craft\i18n\Locale;
use craft\models\Site;
use DateTime;
use Illuminate\Support\Collection;
use Throwable;
use Twig\Markup;
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
 * @property AddressElement|null $billingAddress
 * @property AddressElement|null $shippingAddress
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
 * @property-read int $totalQty the total number of items
 * @property-read int $totalWeight
 * @property-read string $orderStatusHtml
 * @property-read string $customerLinkHtml
 * @property-read string $adjustmentSubtotalAsCurrency
 * @property-read string $adjustmentsTotalAsCurrency
 * @property-read string $itemSubtotalAsCurrency
 * @property-read string $itemTotalAsCurrency
 * @property-read string $outstandingBalanceAsCurrency
 * @property-read string $totalPaidAsCurrency
 * @property-read string $totalAsCurrency
 * @property-read string $totalPriceAsCurrency
 * @property-read string $totalSaleAmountAsCurrency
 * @property-read string $totalTaxAsCurrency
 * @property-read string $totalTaxIncludedAsCurrency
 * @property-read string $totalShippingCostAsCurrency
 * @property-read string $totalDiscountAsCurrency
 * @property-read string $storedTotalPriceAsCurrency
 * @property-read string $storedTotalPaidAsCurrency
 * @property-read string $storedItemTotalAsCurrency
 * @property-read string $storedItemSubtotalAsCurrency
 * @property-read string $storedTotalShippingCostAsCurrency
 * @property-read string $storedTotalDiscountAsCurrency
 * @property-read string $storedTotalTaxAsCurrency
 * @property-read string $storedTotalTaxIncludedAsCurrency
 * @property-read Site|null $orderSite
 * @property null|array|AddressElement $estimatedBillingAddress
 * @property float $totalDiscount
 * @property null|array|AddressElement $estimatedShippingAddress
 * @property float $totalTaxIncluded
 * @property float $totalTax
 * @property float $totalShippingCost
 * @property ShippingMethodOption[] $availableShippingMethodOptions
 * @property-read float|int $totalAuthorized
 * @property float $paymentAmount
 * @property-read null|string $loadCartUrl
 * @property-read array $metadata
 * @property-read Transaction[] $transactions
 * @customer Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Order extends Element
{
    use OrderValidatorsTrait;
    use OrderElementTrait;
    use OrderNoticesTrait;

    /**
     * Payments exceed order total.
     */
    const PAID_STATUS_OVERPAID = 'overPaid';

    /**
     * Payments equal order total.
     */
    const PAID_STATUS_PAID = 'paid';

    /**
     * Payments less than order total.
     */
    const PAID_STATUS_PARTIAL = 'partial';

    /**
     * Payments total zero on non-free order.
     */
    const PAID_STATUS_UNPAID = 'unpaid';

    /**
     * Recalculates line items, populates from purchasables, and regenerates adjustments.
     */
    const RECALCULATION_MODE_ALL = 'all';

    /**
     * Recalculates adjustments only; does not recalculate line items or populate from purchasables.
     */
    const RECALCULATION_MODE_ADJUSTMENTS_ONLY = 'adjustmentsOnly';

    /**
     * Does not recalculate anything on the order.
     */
    const RECALCULATION_MODE_NONE = 'none';

    /**
     * Order created from the front end.
     */
    const ORIGIN_WEB = 'web';

    /**
     * Order created from the control panel.
     */
    const ORIGIN_CP = 'cp';

    /**
     * Order created by a remote source.
     */
    const ORIGIN_REMOTE = 'remote';

    /**
     * @event \yii\base\Event The event that is triggered before a new line item has been added to the order.
     *
     * ```php
     * use craft\commerce\elements\Order;
     * use craft\commerce\models\LineItem;
     * use craft\commerce\events\AddLineItemEvent;
     * use yii\base\Event;
     *
     * Event::on(
     *     Order::class,
     *     Order::EVENT_BEFORE_ADD_LINE_ITEM,
     *     function(AddLineItemEvent $event) {
     *         // @var LineItem $lineItem
     *         $lineItem = $event->lineItem;
     *         // @var bool $isNew
     *         $isNew = $event->isNew;
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
     *     Order::EVENT_AFTER_APPLY_ADD_LINE_ITEM,
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
    const EVENT_AFTER_APPLY_ADD_LINE_ITEM = 'afterApplyAddLineItemToOrder';

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
     * ```
     */
    const EVENT_AFTER_ADD_LINE_ITEM = 'afterAddLineItemToOrder';

    /**
     * @event \yii\base\Event The event that is triggered after a line item has been removed from an order.
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
    const EVENT_AFTER_REMOVE_LINE_ITEM = 'afterRemoveLineItemFromOrder';

    /**
     * @event \yii\base\Event The event that is triggered after a line item has been removed from an order.
     *
     * ```php
     * use craft\commerce\elements\Order;
     * use craft\commerce\events\LineItemEvent;
     * use craft\commerce\models\LineItem;
     * use yii\base\Event;
     *
     * Event::on(
     *     Order::class,
     *     Order::EVENT_AFTER_APPLY_REMOVE_LINE_ITEM,
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
    const EVENT_AFTER_APPLY_REMOVE_LINE_ITEM = 'afterApplyRemoveLineItemFromOrder';

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
     * @event \yii\base\Event This event is raised after an order is customerized in full and completed
     *
     * Plugins can get notified after an order is customerized in full and completed
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
     * @var string|null Number
     * ---
     * ```php
     * echo $order->number;
     * ```
     * ```twig
     * {{ order.number }}
     * ```
     */
    public ?string $number = null;

    /**
     * This is the reference number generated once the order was completed.
     * While the order is a cart, this is null.
     *
     * @var string|null Reference
     * ---
     * ```php
     * echo $order->reference;
     * ```
     * ```twig
     * {{ order.reference }}
     * ```
     */
    public ?string $reference = null;

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
    public ?string $couponCode = null;

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
    public bool $isCompleted = false;

    /**
     * The date and time this order was completed
     *
     * @var DateTime|null Date ordered
     * ---
     * ```php
     * echo $order->dateOrdered;
     * ```
     * ```twig
     * {{ order.dateOrdered }}
     * ```
     */
    public ?DateTime $dateOrdered = null;

    /**
     * The date and time this order was paid in full.
     *
     * @var DateTime|null Date paid
     * ---
     * ```php
     * echo $order->datePaid;
     * ```
     * ```twig
     * {{ order.datePaid }}
     * ```
     */
    public ?DateTime $datePaid = null;

    /**
     * The date and time this order was customerized in full.
     * This may the same date as datePaid if the order was paid immediately.
     *
     * @var DateTime|null Date customerized
     * ---
     * ```php
     * echo $order->dateAuthorized;
     * ```
     * ```twig
     * {{ order.dateAuthorized }}
     * ```
     */
    public ?DateTime $dateAuthorized = null;

    /**
     * The currency of the order (ISO code)
     *
     * @var string|null Currency
     * ---
     * ```php
     * echo $order->currency;
     * ```
     * ```twig
     * {{ order.currency }}
     * ```
     */
    public ?string $currency = null;

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
    public ?int $gatewayId = null;

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
    public ?string $lastIp = null;

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
    public ?string $message = null;

    /**
     * The current URL the order should return to after successful payment.
     * This is stored on the order as we may be redirected off-site for payments.
     *
     * @var string|null Return URL
     * ---
     * ```php
     * echo $order->returnUrl;
     * ```
     * ```twig
     * {{ order.returnUrl }}
     * ```
     */
    public ?string $returnUrl = null;

    /**
     * The current URL the order should return to if the customer cancels payment off-site.
     * This is stored on the order as we may be redirected off-site for payments.
     *
     * @var string|null Cancel URL
     * ---
     * ```php
     * echo $order->cancelUrl;
     * ```
     * ```twig
     * {{ order.cancelUrl }}
     * ```
     */
    public ?string $cancelUrl = null;

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
    public ?int $orderStatusId = null;

    /**
     * The language the cart was created in.
     *
     * @var string|null The language the order was made in.
     * ---
     * ```php
     * echo $order->orderLanguage;
     * ```
     * ```twig
     * {{ order.orderLanguage }}
     * ```
     */
    public ?string $orderLanguage = null;

    /**
     * The site the order was created in.
     *
     * @var int|null Order site ID
     * ---
     * ```php
     * echo $order->orderSiteId;
     * ```
     * ```twig
     * {{ order.orderSiteId }}
     * ```
     */
    public ?int $orderSiteId = null;


    /**
     * The origin of the order when it was first created.
     * Values can be 'web', 'cp', or 'api'
     *
     * @var string|null Order origin
     * ---
     * ```php
     * echo $order->origin;
     * ```
     * ```twig
     * {{ order.origin }}
     * ```
     */
    public ?string $origin = null;

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
    public ?int $billingAddressId = null;

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
    public ?int $shippingAddressId = null;


    /**
     * Whether the shipping address should be made the primary address of the
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
    public bool $makePrimaryShippingAddress = false;

    /**
     * Whether the billing address should be made the primary address of the
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
    public bool $makePrimaryBillingAddress = false;

    /**
     * Whether the shipping address should be the same address as the order’s
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
    public bool $shippingSameAsBilling = false;

    /**
     * Whether the billing address should be the same address as the order’s
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
    public bool $billingSameAsShipping = false;

    /**
     * @var int|null Estimated Billing address ID
     * @since 2.2
     */
    public ?int $estimatedBillingAddressId = null;

    /**
     * @var int|null Estimated Shipping address ID
     * @since 2.2
     */
    public ?int $estimatedShippingAddressId = null;

    /**
     * @var int|null The billing address ID that was selected from the customer’s address book,
     * which populated the billing address on the order.
     * @since 4.0
     */
    public ?int $sourceBillingAddressId = null;

    /**
     * @var int|null The shipping address ID that was selected from the customer’s address book,
     * which populated the shipping address on the order.
     * @since 4.0
     */
    public ?int $sourceShippingAddressId = null;

    /**
     * @var bool Whether estimated billing address should be set to the same address as estimated shipping
     * @since 2.2
     */
    public bool $estimatedBillingSameAsShipping = false;

    /**
     * @var string|null Shipping Method Handle
     */
    public ?string $shippingMethodHandle = '';

    /**
     * @var string|null Shipping Method Name
     * @since 3.2.0
     */
    public ?string $shippingMethodName = null;

    /**
     * @param ?int $oldStatusId
     * @param ?int $currentOrderStatId
     * @return void
     */
    private function _saveOrderHistory(?int $oldStatusId, ?int $currentOrderStatId): void
    {
        $hasNewStatus = ($oldStatusId !== $currentOrderStatId);
        if ($this->isCompleted && $hasNewStatus) {
            if (!Plugin::getInstance()->getOrderHistories()->createOrderHistoryFromOrder($this, $oldStatusId)) {
                Craft::error('Error saving order history after order save.', __METHOD__);
            }
        }
    }

    /**
     * @var int|null Customer’s ID
     */
    private ?int $_customerId = null;

    /**
     * Whether the email address on the order should be used to register
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
    public bool $registerUserOnOrderComplete = false;

    /**
     * The current payment source that should be used to make payments on the
     * order. If this is set, the `gatewayId` will also be set to the related
     * gateway.
     *
     * @var int|null Payment source ID
     * ---
     * ```php
     * echo $order->paymentSourceId;
     * ```
     * ```twig
     * {{ order.paymentSourceId }}
     * ```
     */
    public ?int $paymentSourceId = null;


    /**
     * @var float|null The total price as stored in the database from last retrieval
     * ---
     * ```php
     * echo $order->storedTotalPrice;
     * ```
     * ```twig
     * {{ order.storedTotalPrice }}
     * ```
     */
    public ?float $storedTotalPrice = null;

    /**
     * @var float|null The total paid as stored in the database from last retrieval
     * ---
     * ```php
     * echo $order->storedTotalPaid;
     * ```
     * ```twig
     * {{ order.storedTotalPaid }}
     * ```
     */
    public ?float $storedTotalPaid = null;

    /**
     * @var float|null The item total as stored in the database from last retrieval
     * ---
     * ```php
     * echo $order->storedItemTotal;
     * ```
     * ```twig
     * {{ order.storedItemTotal }}
     * ```
     */
    public ?float $storedItemTotal = null;

    /**
     * @var float|null The item subtotal as stored in the database from last retrieval
     * @since 3.2.4
     * ---
     * ```php
     * echo $order->storedItemSubtotal;
     * ```
     * ```twig
     * {{ order.storedItemSubtotal }}
     * ```
     */
    public ?float $storedItemSubtotal = null;

    /**
     * @var float|null The total shipping cost adjustments as stored in the database from last retrieval
     * ---
     * ```php
     * echo $order->storedTotalShippingCost;
     * ```
     * ```twig
     * {{ order.storedTotalShippingCost }}
     * ```
     */
    public ?float $storedTotalShippingCost = null;

    /**
     * @var float|null The total of discount adjustments as stored in the database from last retrieval
     * ---
     * ```php
     * echo $order->storedTotalDiscount;
     * ```
     * ```twig
     * {{ order.storedTotalDiscount }}
     * ```
     */
    public ?float $storedTotalDiscount = null;

    /**
     * @var float|null The total tax adjustments as stored in the database from last retrieval
     * ---
     * ```php
     * echo $order->storedTotalTax;
     * ```
     * ```twig
     * {{ order.storedTotalTax }}
     * ```
     */
    public ?float $storedTotalTax = null;

    /**
     * @var float|null The total tax included  adjustments as stored in the database from last retrieval
     * ---
     * ```php
     * echo $order->storedTotalTaxIncluded;
     * ```
     * ```twig
     * {{ order.storedTotalTaxIncluded }}
     * ```
     */
    public ?float $storedTotalTaxIncluded = null;


    /**
     * @var string|null
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
    private ?string $_recalculationMode = null;

    /**
     * @var AddressElement|null
     * @see Order::setShippingAddress() To set the current shipping address
     * @see Order::getShippingAddress() To get the current shipping address
     * ---
     * ```php
     * if ($order->shippingAddress) {
     *     echo $order->shippingAddress->firstName;
     * }
     * ```
     * ```twig
     * {% if order.shippingAddress %}
     *   {{ order.shippingAddress.firstName }}
     * {% endif %}
     * ```
     */
    private ?AddressElement $_shippingAddress = null;

    /**
     * @var AddressElement|null
     * @see Order::setBillingAddress() To set the current billing address
     * @see Order::getBillingAddress() To get the current billing address
     * ---
     * ```php
     * if ($order->billingAddress) {
     *     echo $order->billingAddress->firstName;
     * }
     * ```
     * ```twig
     * {% if order.billingAddress %}
     *   {{ order.billingAddress.firstName }}
     * {% endif %}
     * ```
     */
    private ?AddressElement $_billingAddress = null;

    /**
     * @var AddressElement|null
     * @since 2.2
     */
    private ?AddressElement $_estimatedShippingAddress = null;

    /**
     * @var AddressElement|null
     * @since 2.2
     */
    private ?AddressElement $_estimatedBillingAddress = null;

    /**
     * @var LineItem[]
     * @see Order::setLineItems() To set the order line items
     * @see Order::getLineItems() To get the order line items
     * ---
     * ```php
     * foreach ($order->getLineItems() as $lineItem) {
     *     echo $lineItem->description';
     * }
     * ```
     * ```twig
     * {% for lineItem in order.lineItems %}
     *   {{ lineItem.description }}
     * {% endfor %}
     * ```
     */
    private array $_lineItems;

    /**
     * @var OrderAdjustment[]|null
     * @see Order::setAdjustments() To set the order adjustments
     * @see Order::setAdjustments() To get the order adjustments
     * ---
     * ```php
     * foreach ($order->getAdjustments() as $adjustment) {
     *     echo $adjustment->amount';
     * }
     * ```
     * ```twig
     * {% for adjustment in order.adjustments %}
     *   {{ adjustment.amount }}
     * {% endfor %}
     * ```
     */
    private ?array $_orderAdjustments = null;

    /**
     * @var string|null
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
    private ?string $_paymentCurrency = null;

    /**
     * @var string|null
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
    private ?string $_email = null;

    /**
     * @var Transaction[]|null
     * @see Order::getTransactions()
     * ---
     * ```php
     * echo $order->transactions;
     * ```
     * ```twig
     * {{ order.transactions }}
     * ```
     */
    private ?array $_transactions = null;

    /**
     * @var User|null|false
     * @see Order::getCustomer()
     * @see Order::setCustomer()
     * ---
     * ```php
     * echo $order->customer;
     * ```
     * ```twig
     * {{ order.customer }}
     * ```
     */
    private User|null|false $_customer;

    /**
     * @var float|null
     * @see Order::setPaymentAmount() To set the order payment amount
     * @see Order::getPaymentAmount() To get the order payment amount
     * ---
     * ```php
     * echo $order->paymentAmount;
     * ```
     * ```twig
     * {{ order.paymentAmount }}
     * ```
     */
    private ?float $_paymentAmount = null;

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        // Set default addresses
        if (!$this->isCompleted && Plugin::getInstance()->getSettings()->autoSetNewCartAddresses) {
            $user = $this->getCustomer();
            if (!$this->shippingAddressId && $user) {
                if ($primaryShippingAddress = $user->getPrimaryShippingAddress()) {
                    $this->setShippingAddress($primaryShippingAddress);
                }
            }

            if (!$this->shippingAddressId && $user) {
                if ($primaryShippingAddress = $user->getPrimaryShippingAddress()) {
                    $this->setBillingAddress($primaryShippingAddress);
                }
            }
        }

        if ($this->orderLanguage === null) {
            $this->orderLanguage = Craft::$app->language;
        }

        if ($this->orderSiteId === null) {
            $this->orderSiteId = Craft::$app->getSites()->getHasCurrentSite() ? Craft::$app->getSites()->getCurrentSite()->id : Craft::$app->getSites()->getPrimarySite()->id;
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

        // Sets a default shipping method
        // Leave this as the last one inside init(), as shipping rules will need access the above default that are set (like currency).
        if (!$this->shippingMethodHandle && !$this->isCompleted && Plugin::getInstance()->getSettings()->autoSetCartShippingMethodOption) {
            $availableMethodOptions = $this->getAvailableShippingMethodOptions();
            $this->shippingMethodHandle = ArrayHelper::firstKey($availableMethodOptions);
        }

        parent::init();
    }

    public function behaviors(): array
    {
        $behaviors = parent::behaviors();

        $behaviors['currencyAttributes'] = [
            'class' => CurrencyAttributeBehavior::class,
            'defaultCurrency' => $this->currency ?? Plugin::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrencyIso(),
            'currencyAttributes' => $this->currencyAttributes(),
            'attributeCurrencyMap' => [],
        ];

        return $behaviors;
    }

    /**
     * @return null|string
     */
    public static function displayName(): string
    {
        return Craft::t('commerce', 'Order');
    }

    /**
     * @inheritdoc
     */
    public static function lowerDisplayName(): string
    {
        return Craft::t('commerce', 'order');
    }

    /**
     * @inheritdoc
     */
    public static function pluralDisplayName(): string
    {
        return Craft::t('commerce', 'Orders');
    }

    /**
     * @inheritdoc
     */
    public static function pluralLowerDisplayName(): string
    {
        return Craft::t('commerce', 'orders');
    }

    /**
     * @inheritdoc
     */
    public function __toString(): string
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

        // If the gateway ID doesn't exist, just drop it.
        if ($this->gatewayId && !$this->getGateway()) {
            $this->gatewayId = null;
        }

        if (!$this->isCompleted) {
            if (Plugin::getInstance()->getSettings()->useBillingAddressForTax && $this->getBillingAddress()) {
                $this->getBillingAddress()->attachBehavior('validateOrganizationTaxId', ValidateOrganizationTaxIdBehavior::class);
            }
            if (!Plugin::getInstance()->getSettings()->useBillingAddressForTax && $this->getShippingAddress()) {
                $this->getShippingAddress()->attachBehavior('validateOrganizationTaxId', ValidateOrganizationTaxIdBehavior::class);
            }
        }

        return parent::beforeValidate();
    }

    /**
     * @inheritdoc
     */
    public function datetimeAttributes(): array
    {
        $attributes = parent::datetimeAttributes();

        $attributes[] = 'dateAuthorized';
        $attributes[] = 'datePaid';
        $attributes[] = 'dateOrdered';
        $attributes[] = 'dateUpdated';

        return $attributes;
    }

    /**
     * @inheritdoc
     */
    public function attributes(): array
    {
        $names = parent::attributes();
        $names[] = 'adjustmentSubtotal';
        $names[] = 'adjustmentsTotal';
        $names[] = 'paymentCurrency';
        $names[] = 'paymentAmount';
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
        $names[] = 'totalWeight';
        return $names;
    }

    /**
     * The attributes on the order that should be made available as formatted currency.
     */
    public function currencyAttributes(): array
    {
        $attributes = [];
        $attributes[] = 'adjustmentSubtotal';
        $attributes[] = 'adjustmentsTotal';
        $attributes[] = 'itemSubtotal';
        $attributes[] = 'itemTotal';
        $attributes[] = 'outstandingBalance';
        $attributes[] = 'paymentAmount';
        $attributes[] = 'totalPaid';
        $attributes[] = 'total';
        $attributes[] = 'totalPrice';
        $attributes[] = 'totalSaleAmount';
        $attributes[] = 'totalTax';
        $attributes[] = 'totalTaxIncluded';
        $attributes[] = 'totalShippingCost';
        $attributes[] = 'totalDiscount';
        $attributes[] = 'storedTotalPrice';
        $attributes[] = 'storedTotalPaid';
        $attributes[] = 'storedItemTotal';
        $attributes[] = 'storedItemSubtotal';
        $attributes[] = 'storedTotalShippingCost';
        $attributes[] = 'storedTotalDiscount';
        $attributes[] = 'storedTotalTax';
        $attributes[] = 'storedTotalTaxIncluded';

        return $attributes;
    }

    public function fields(): array
    {
        $fields = parent::fields();

        foreach ($this->datetimeAttributes() as $attribute) {
            $fields[$attribute] = static function($model, $attribute) {
                if (!empty($model->$attribute)) {
                    $formatter = Craft::$app->getFormatter();

                    return [
                        'date' => $formatter->asDate($model->$attribute, Locale::LENGTH_SHORT),
                        'time' => $formatter->asTime($model->$attribute, Locale::LENGTH_SHORT),
                    ];
                }

                return $model->$attribute;
            };
        }

        //TODO Remove this when we require Craft 3.5 and the bahaviour can support the define fields event  #COM-27
        if ($this->getBehavior('currencyAttributes')) {
            $fields = array_merge($fields, $this->getBehavior('currencyAttributes')->currencyFields());
        }

        $fields['paidStatusHtml'] = 'paidStatusHtml';
        $fields['customerLinkHtml'] = 'customerLinkHtml';
        $fields['orderStatusHtml'] = 'orderStatusHtml';
        $fields['totalTax'] = 'totalTax';
        $fields['totalTaxIncluded'] = 'totalTaxIncluded';
        $fields['totalShippingCost'] = 'totalShippingCost';
        $fields['totalDiscount'] = 'totalDiscount';

        return $fields;
    }

    /**
     * @inheritdoc
     */
    public function extraFields(): array
    {
        $names = parent::extraFields();
        $names[] = 'adjustments';
        $names[] = 'availableShippingMethodOptions';
        $names[] = 'billingAddress';
        $names[] = 'customer';
        $names[] = 'estimatedBillingAddress';
        $names[] = 'estimatedShippingAddress';
        $names[] = 'gateway';
        $names[] = 'histories';
        $names[] = 'loadCartUrl';
        $names[] = 'nestedTransactions';
        $names[] = 'notices';
        $names[] = 'orderSite';
        $names[] = 'orderStatus';
        $names[] = 'pdfUrl';
        $names[] = 'shippingAddress';
        $names[] = 'shippingMethod';
        $names[] = 'transactions';
        return $names;
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            // Address models are valid
            [['billingAddress', 'shippingAddress'], 'validateAddress'],

            // Are the addresses both being set to each other.
            [
                ['billingAddress', 'shippingAddress'], 'validateAddressReuse',
                'when' => function($model) {
                    /** @var Order $model */
                    return !$model->isCompleted;
                },
            ],

            // Line items are valid?
            [['lineItems'], 'validateLineItems'],

            // Coupon Code valid?
            [['couponCode'], 'validateCouponCode'],

            [['gatewayId'], 'number', 'integerOnly' => true],
            [['gatewayId'], 'validateGatewayId'],
            [['shippingAddressId'], 'number', 'integerOnly' => true],
            [['billingAddressId'], 'number', 'integerOnly' => true],

            [['paymentCurrency'], 'validatePaymentCurrency'],

            [['paymentSourceId'], 'number', 'integerOnly' => true],
            [['paymentSourceId'], 'validatePaymentSourceId'],
            [['email'], 'email'],

            [['number', 'user'], 'safe'],
        ]);
    }

    /**
     * Updates the paid status and paid date of the order, and marks as complete if the order is paid or customerized.
     */
    public function updateOrderPaidInformation(): void
    {
        $this->_transactions = null; // clear order's transaction cache

        $paidInFull = !$this->hasOutstandingBalance();
        $customerizedInFull = $this->getTotalAuthorized() >= $this->getTotalPrice();

        $justPaid = $paidInFull && $this->datePaid == null;
        $justAuthorized = $customerizedInFull && $this->dateAuthorized == null;

        $canComplete = ($this->getTotalAuthorized() + $this->getTotalPaid()) > 0;

        // If it is no longer paid in full, set datePaid to null
        if (!$paidInFull) {
            $this->datePaid = null;
        }

        // If it is no longer customerized in full, set dateAuthorized to null
        if (!$customerizedInFull) {
            $this->dateAuthorized = null;
        }

        // If it was just paid set the date paid to now.
        if ($justPaid) {
            $this->datePaid = new DateTime();
        }

        // If it was just customerized set the date customerized to now.
        if ($justAuthorized) {
            $this->dateAuthorized = new DateTime();
        }

        // Lock for recalculation
        $originalRecalculationMode = $this->getRecalculationMode();
        $this->setRecalculationMode(self::RECALCULATION_MODE_NONE);

        // Saving the order will update the datePaid as set above and also update the paidStatus.
        Craft::$app->getElements()->saveElement($this, false);

        // If the order is now paid or customerized in full, lets mark it as complete if it has not already been.
        if (!$this->isCompleted) {
            $totalAuthorized = $this->getTotalAuthorized();
            if ($totalAuthorized >= $this->getTotalPrice() || $paidInFull || $canComplete) {
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
     * Marks the order as complete and sets the default order status, then saves the order.
     *
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
        $this->dateOrdered = new DateTime();

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

        $this->clearNotices(); // Customer notices are assessed as being delivered once the customer decides to complete the order.
        $success = Craft::$app->getElements()->saveElement($this, false);

        if (!$success) {
            Craft::error(Craft::t('commerce', 'Could not mark order {number} as complete. Order save failed during order completion with errors: {order}',
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
    public function afterOrderComplete(): void
    {
        // Run order complete handlers directly.
        Plugin::getInstance()->getDiscounts()->orderCompleteHandler($this);
        Plugin::getInstance()->getCustomers()->orderCompleteHandler($this);

        foreach ($this->getLineItems() as $lineItem) {
            Plugin::getInstance()->getLineItems()->orderCompleteHandler($lineItem, $this);
        }

        // Raising the 'afterCompleteOrder' event
        if ($this->hasEventHandlers(self::EVENT_AFTER_COMPLETE_ORDER)) {
            $this->trigger(self::EVENT_AFTER_COMPLETE_ORDER);
        }
    }

    /**
     * Removes a specific line item from the order.
     */
    public function removeLineItem(LineItem $lineItem): void
    {
        $lineItems = $this->getLineItems();
        foreach ($lineItems as $key => $item) {
            if (($item->id !== null && $lineItem->id == $item->id) || $lineItem === $item) {
                unset($lineItems[$key]);
                $this->setLineItems($lineItems);
            }
        }

        if ($this->hasEventHandlers(self::EVENT_AFTER_REMOVE_LINE_ITEM)) {
            $this->trigger(self::EVENT_AFTER_REMOVE_LINE_ITEM, new LineItemEvent([
                'lineItem' => $lineItem,
            ]));
        }
    }

    /**
     * Adds a line item to the order. Updates the line item if the ID of that line item is already in the cart.
     */
    public function addLineItem(LineItem $lineItem): void
    {
        $lineItems = $this->getLineItems();
        $isNew = ($lineItem->id === null);

        if ($isNew && $this->hasEventHandlers(self::EVENT_BEFORE_ADD_LINE_ITEM)) {
            $lineItemEvent = new AddLineItemEvent(compact('lineItem', 'isNew'));
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
            array_unshift($lineItems, $lineItem);
        }

        $this->setLineItems($lineItems);

        // Raising the 'afterAddLineItemToOrder' event
        if ($this->hasEventHandlers(self::EVENT_AFTER_ADD_LINE_ITEM)) {
            $this->trigger(self::EVENT_AFTER_ADD_LINE_ITEM, new LineItemEvent([
                'lineItem' => $lineItem,
                'isNew' => !$replaced,
            ]));
        }
    }

    /**
     * Gets the recalculation mode of the order
     */
    public function getRecalculationMode(): string
    {
        return $this->_recalculationMode;
    }

    /**
     * Sets the recalculation mode of the order
     */
    public function setRecalculationMode(string $value): void
    {
        $this->_recalculationMode = $value;
    }

    /**
     * Regenerates all adjusters and updates line items, depending on the current recalculationMode
     *
     * @throws Exception
     */
    public function recalculate(): void
    {
        if (!$this->id) {
            throw new InvalidCallException('Do not recalculate an order that has not been saved');
        }

        if ($this->hasErrors()) {
            Craft::getLogger()->log(Craft::t('commerce', 'Do not call recalculate on the order (Number: {orderNumber}) if errors are present.', ['orderNumber' => $this->number]), Logger::LEVEL_INFO);
            return;
        }

        if ($this->getRecalculationMode() == self::RECALCULATION_MODE_NONE) {
            return;
        }

        if ($this->getRecalculationMode() == self::RECALCULATION_MODE_ALL) {

            // Make sure we set a default shipping method option
            if (!$this->isCompleted && Plugin::getInstance()->getSettings()->autoSetCartShippingMethodOption) {
                $availableMethodOptions = $this->getAvailableShippingMethodOptions();
                if (!$this->shippingMethodHandle || !isset($availableMethodOptions[$this->shippingMethodHandle])) {
                    $this->shippingMethodHandle = ArrayHelper::firstKey($availableMethodOptions);
                }
            }

            if (!$this->shippingMethodHandle) {
                $this->shippingMethodName = null;
            } else if ($shippingMethod = $this->getShippingMethod()) {
                $this->shippingMethodName = $shippingMethod->getName();
            }

            $lineItemRemoved = false;
            foreach ($this->getLineItems() as $item) {
                $originalSalePrice = $item->getSalePrice();
                $originalSalePriceAsCurrency = $item->salePriceAsCurrency;
                if ($item->refreshFromPurchasable()) {
                    if ($originalSalePrice > $item->salePrice) {
                        $message = Craft::t('commerce', 'The price of {description} was reduced from {originalSalePriceAsCurrency} to {newSalePriceAsCurrency}', ['originalSalePriceAsCurrency' => $originalSalePriceAsCurrency, 'newSalePriceAsCurrency' => $item->salePriceAsCurrency, 'description' => $item->getDescription()]);
                        /** @var OrderNotice $notice */
                        $notice = Craft::createObject([
                            'class' => OrderNotice::class,
                            'attributes' => [
                                'type' => 'lineItemSalePriceChanged',
                                'attribute' => "lineItems.{$item->id}.salePrice",
                                'message' => $message,
                            ],
                        ]);
                        $this->addNotice($notice);
                    }

                    if ($originalSalePrice < $item->salePrice) {
                        $message = Craft::t('commerce', 'The price of {description} increased from {originalSalePriceAsCurrency} to {newSalePriceAsCurrency}', ['originalSalePriceAsCurrency' => $originalSalePriceAsCurrency, 'newSalePriceAsCurrency' => $item->salePriceAsCurrency, 'description' => $item->getDescription()]);
                        /** @var OrderNotice $notice */
                        $notice = Craft::createObject([
                            'class' => OrderNotice::class,
                            'attributes' => [
                                'type' => 'lineItemSalePriceChanged',
                                'attribute' => "lineItems.{$item->id}.salePrice",
                                'message' => $message,
                            ],
                        ]);
                        $this->addNotice($notice);
                    }
                } else {
                    $message = Craft::t('commerce', '{description} is no longer available.', ['description' => $item->getDescription()]);
                    /** @var OrderNotice $notice */
                    $notice = Craft::createObject([
                        'class' => OrderNotice::class,
                        'attributes' => [
                            'message' => $message,
                            'type' => 'lineItemRemoved',
                            'attribute' => 'lineItems',
                        ],
                    ]);
                    $this->addNotice($notice);
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
                $adjuster = Craft::createObject($adjuster);
                $adjustments = $adjuster->adjust($this);
                $this->setAdjustments(array_merge($this->getAdjustments(), $adjustments));
            }
        }

        if ($this->getRecalculationMode() == self::RECALCULATION_MODE_ALL) {        // Since shipping adjusters run on the original price, pre discount, let's recalculate
            // if the currently selected shipping method is now not available after adjustments have run.
            $availableMethodOptions = $this->getAvailableShippingMethodOptions();
            if ($this->shippingMethodHandle) {
                if (!isset($availableMethodOptions[$this->shippingMethodHandle]) || empty($availableMethodOptions)) {
                    $this->shippingMethodHandle = ArrayHelper::firstKey($availableMethodOptions);
                    $message = Craft::t('commerce', 'The previously-selected shipping method is no longer available.');
                    /** @var OrderNotice $orderNotice */
                    $orderNotice = Craft::createObject([
                        'class' => OrderNotice::class,
                        'attributes' => [
                            'type' => 'shippingMethodChanged',
                            'attribute' => 'shippingMethodHandle',
                            'message' => $message,
                        ],
                    ]);

                    $this->addNotice($orderNotice);
                    $this->recalculate();
                }
            }
        }
    }

    /**
     * @return ShippingMethodOption[]
     *
     * @since 3.1
     */
    public function getAvailableShippingMethodOptions(): array
    {
        $matchingMethods = Plugin::getInstance()->getShippingMethods()->getMatchingShippingMethods($this);
        $methods = Plugin::getInstance()->getShippingMethods()->getAllShippingMethods();
        $matchingMethodHandles = ArrayHelper::getColumn($matchingMethods, 'handle');

        $options = [];
        $attributes = (new ShippingMethod())->attributes();

        foreach ($methods as $method) {
            $option = new ShippingMethodOption();
            $option->setOrder($this);
            foreach ($attributes as $attribute) {
                $option->$attribute = $method->$attribute;
            }

            $option->matchesOrder = ArrayHelper::isIn($method->handle, $matchingMethodHandles);
            $option->price = $method->getPriceForOrder($this);

            // Add all methods if completed, and only the matching methods when it is not completed.
            if ($this->isCompleted || $option->matchesOrder) {
                $options[$option->handle] = $option;
            }
        }

        return $options;
    }

    /**
     * @inheritdoc
     */
    public function afterSave(bool $isNew): void
    {
        // Make sure addresses are set before recalculation so that on the next page load
        // the correct adjustments and totals are shown
        if ($this->shippingSameAsBilling) {
            $this->setShippingAddress($this->getBillingAddress());
        }

        if ($this->billingSameAsShipping) {
            $this->setBillingAddress($this->getShippingAddress());
        }

        // TODO: Move the recalculate to somewhere else. Saving should be for saving only #COM-40
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

        $dateOrdered = $this->dateOrdered;
        if (!$dateOrdered && $orderRecord->isCompleted) {
            $dateOrdered = Db::prepareDateForDb(new DateTime());
        }
        $orderRecord->dateOrdered = $dateOrdered;

        $orderRecord->datePaid = $this->datePaid ?: null;
        $orderRecord->dateAuthorized = $this->dateAuthorized ?: null;
        $orderRecord->shippingMethodHandle = $this->shippingMethodHandle;
        $orderRecord->shippingMethodName = $this->shippingMethodName;
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
        $orderRecord->orderSiteId = $this->orderSiteId;
        $orderRecord->origin = $this->origin;
        $orderRecord->paymentCurrency = $this->paymentCurrency;
        $orderRecord->customerId = $this->getCustomerId();
        $orderRecord->registerUserOnOrderComplete = $this->registerUserOnOrderComplete;
        $orderRecord->returnUrl = $this->returnUrl;
        $orderRecord->cancelUrl = $this->cancelUrl;
        $orderRecord->message = $this->message;
        $orderRecord->paidStatus = $this->getPaidStatus();
        $orderRecord->recalculationMode = $this->getRecalculationMode();
        $orderRecord->sourceShippingAddressId = $this->sourceShippingAddressId;
        $orderRecord->sourceBillingAddressId = $this->sourceBillingAddressId;

        // We want to always have the same date as the element table, based on the logic for updating these in the element service i.e resaving
        $orderRecord->dateUpdated = $this->dateUpdated;
        $orderRecord->dateCreated = $this->dateCreated;

        if ($shippingAddress = $this->getShippingAddress()) {
            Craft::$app->getElements()->saveElement($shippingAddress, false);
            $orderRecord->shippingAddressId = $shippingAddress->id;
            $this->setShippingAddress($shippingAddress);
            // Set primary shipping if asked
            if($this->makePrimaryShippingAddress && $this->getCustomer()) {
                Plugin::getInstance()->getCustomers()->savePrimaryShippingAddressId($this->getCustomer(), $this->getShippingAddress()->id);
            }
        } else {
            $orderRecord->shippingAddressId = null;
            $this->setShippingAddress(null);
        }

        if ($billingAddress = $this->getBillingAddress()) {
            Craft::$app->getElements()->saveElement($billingAddress, false);
            $orderRecord->billingAddressId = $billingAddress->id;
            $this->setBillingAddress($billingAddress);
            // Set primary billing if asked
            if($this->makePrimaryBillingAddress && $this->getCustomer()) {
                Plugin::getInstance()->getCustomers()->savePrimaryBillingAddressId($this->getCustomer(), $this->getBillingAddress()->id);
            }
        } else {
            $orderRecord->billingAddressId = null;
            $this->setBillingAddress(null);
        }

        if ($estimatedShippingAddress = $this->getEstimatedShippingAddress()) {
            Craft::$app->getElements()->saveElement($estimatedShippingAddress, false);
            $orderRecord->estimatedShippingAddressId = $estimatedShippingAddress->id;
            $this->setEstimatedShippingAddress($estimatedShippingAddress);

            // If estimate billing same as shipping set it here
            if ($this->estimatedBillingSameAsShipping) {
                $orderRecord->estimatedBillingAddressId = $estimatedShippingAddress->id;
                $this->setEstimatedBillingAddress($estimatedShippingAddress);
            }
        }

        if (!$this->estimatedBillingSameAsShipping && $estimatedBillingAddress = $this->getEstimatedBillingAddress()) {
            Craft::$app->getElements()->saveElement($estimatedBillingAddress, false);
            $orderRecord->estimatedBillingAddressId = $estimatedBillingAddress->id;
            $this->setEstimatedBillingAddress($estimatedBillingAddress);
        }

        $orderRecord->save(false);

        $this->_saveAdjustments();
        $this->_saveLineItems();
        $this->_saveNotices();
        $this->_saveOrderHistory($oldStatusId, $orderRecord->orderStatusId);

        parent::afterSave($isNew);
    }

    /**
     * @inheritdoc
     */
    protected function isEditable(): bool
    {
        return Craft::$app->getUser()->checkPermission('commerce-manageOrders');
    }

    public function getShortNumber(): string
    {
        return substr($this->number, 0, 7);
    }

    /**
     * @inheritdoc
     */
    public function getLink(): ?Markup
    {
        return Template::raw("<a href='" . $this->getCpEditUrl() . "'>" . ($this->reference ?: $this->getShortNumber()) . '</a>');
    }

    /**
     * @inheritdoc
     */
    public function getCpEditUrl(): ?string
    {
        return UrlHelper::cpUrl('commerce/orders/' . $this->id);
    }

    /**
     * Returns the URL to the order’s PDF invoice.
     *
     * @param string|null $option The option that should be available to the PDF template (e.g. “receipt”)
     * @param string|null $pdfHandle The handle of the PDF to use. If none is passed the default PDF is used.
     * @return string|null The URL to the order’s PDF invoice, or null if the PDF template doesn’t exist
     */
    public function getPdfUrl(string $option = null, string $pdfHandle = null): ?string
    {
        $path = "commerce/downloads/pdf";
        $params = [];
        $params['number'] = $this->number;

        if ($option) {
            $params['option'] = $option;
        }

        if ($pdfHandle !== null) {
            $params['pdfHandle'] = $pdfHandle;
        }

        return UrlHelper::actionUrl($path, $params);
    }

    /**
     * Returns the URL to the cart’s load action url
     *
     * @return string|null The URL to the order’s load cart URL, or null if the cart is an order
     * @noinspection PhpUnused
     */
    public function getLoadCartUrl(): ?string
    {
        if ($this->isCompleted) {
            return null;
        }

        $path = 'commerce/cart/load-cart';

        $params = [];
        $params['number'] = $this->number;

        return UrlHelper::actionUrl($path, $params);
    }

    /**
     * Returns the order customer ID.
     *
     * @return int|null
     * @since 4.0.0
     */
    public function getCustomerId(): ?int
    {
        return $this->_customerId;
    }

    /**
     * Sets the order customer ID.
     *
     * @param int|int[]|null $customerId
     * @since 4.0.0
     */
    public function setCustomerId(mixed $customerId): void
    {
        if (is_array($customerId)) {
            $this->_customerId = reset($customerId) ?: null;
        } else {
            $this->_customerId = $customerId;
        }

        $this->_customer = null;
    }

    /**
     * Returns the order's customer.
     *
     * ---
     * ```php
     * $customer = $order->customer;
     * ```
     * ```twig
     * <p>By {{ order.customer.name }}</p>
     * ```
     *
     * @return User|null|false
     */
    public function getCustomer(): User|null|false
    {
        if (!isset($this->_customer)) {
            if (!$this->getCustomerId()) {
                return null;
            }

            if (($this->_customer = Craft::$app->getUsers()->getUserById($this->getCustomerId())) === null) {
                $this->_customer = false;
            }
        }

        if ($this->_customer) {
            $this->_email = $this->_customer->email;
        }

        return $this->_customer ?: null;
    }

    /**
     * Sets the order's customer.
     *
     * @param User|null $customer
     */
    public function setCustomer(?User $customer = null): void
    {
        if ($customer && $this->_customer = $customer) {
            $this->setCustomerId($customer->id);
        }
    }

    /**
     * @deprecated in 4.0.0. Use [[getCustomer()]] instead.
     */
    public function getUser(): ?User
    {
        Craft::$app->getDeprecator()->log('Order::getUser()', 'The `Order::getUser()` is deprecated, use the `Order::getCustomer()` instead.');
        return $this->getCustomer();
    }

    /**
     * Sets the orders user based on the email address provided.
     *
     * @param string|null $email
     */
    public function setEmail(?string $email): void
    {
        if (!$email) {
            $this->_customer = null;
            $this->_customerId = null;
            $this->_email = null;
            return;
        }

        if ($this->_email === $email) {
            return;
        }

        $user = Craft::$app->getUsers()->ensureUserByEmail($email);
        $this->_email = $email;
        $this->setCustomer($user);
    }

    /**
     * Returns the email for this order. Will always be the registered users email if the order's customer is related to a user.
     */
    public function getEmail(): ?string
    {
        if ($user = $this->getCustomer()) {
            $this->_email = $user->email;
        }

        return $this->_email ?? null;
    }

    public function getIsPaid(): bool
    {
        return !$this->hasOutstandingBalance() && $this->isCompleted;
    }

    /**
     * @noinspection PhpUnused
     */
    public function getIsUnpaid(): bool
    {
        return $this->hasOutstandingBalance();
    }

    /**
     * Returns the paymentAmount for this order.
     *
     * @throws CurrencyException
     */
    public function getPaymentAmount(): float
    {
        $outstandingBalanceInPaymentCurrency = Plugin::getInstance()->getPaymentCurrencies()->convertCurrency($this->getOutstandingBalance(), $this->currency, $this->paymentCurrency);

        if (isset($this->_paymentAmount) && $this->_paymentAmount >= 0 && $this->_paymentAmount <= $outstandingBalanceInPaymentCurrency) {
            return $this->_paymentAmount;
        }

        return $outstandingBalanceInPaymentCurrency;
    }

    /**
     * Sets the order's payment amount in the order's currency. This amount is not persisted.
     *
     * @throws CurrencyException
     * @throws InvalidConfigException
     */
    public function setPaymentAmount(float $amount): void
    {
        $paymentCurrency = Plugin::getInstance()->getPaymentCurrencies()->getPaymentCurrencyByIso($this->getPaymentCurrency());
        $amount = Currency::round($amount, $paymentCurrency);
        $this->_paymentAmount = $amount;
    }

    /**
     * Returns whether the payment amount currently set is a partial amount of the order's outstanding balance.
     *
     * @throws CurrencyException
     * @throws InvalidConfigException
     * @since 3.4.10
     */
    public function isPaymentAmountPartial(): bool
    {
        $paymentAmountInPrimaryCurrency = Plugin::getInstance()->getPaymentCurrencies()->convertCurrency($this->getPaymentAmount(), $this->getPaymentCurrency(), $this->currency, true);

        return $paymentAmountInPrimaryCurrency < $this->getOutstandingBalance();
    }

    /**
     * What is the status of the orders payment
     */
    public function getPaidStatus(): string
    {
        if ($this->getIsPaid() && $this->getTotal() > 0 && $this->getTotalPaid() > $this->getTotal()) {
            return self::PAID_STATUS_OVERPAID;
        }

        if ($this->getIsPaid()) {
            return self::PAID_STATUS_PAID;
        }

        if ($this->getTotalPaid() > 0) {
            return self::PAID_STATUS_PARTIAL;
        }

        return self::PAID_STATUS_UNPAID;
    }

    /**
     * Customer User link represented as HTML
     *
     * @return string
     * @since 3.0
     */
    public function getCustomerLinkHtml(): string
    {
        $html = '';
        if ($user = $this->getCustomer()) {
            $html = Html::tag('a', $user->email, ['href' => $user->getCpEditUrl()]);
        }

        return $html;
    }

    public function getOrderStatusHtml(): string
    {
        if ($status = $this->getOrderStatus()) {
            return '<span class="commerceStatusLabel"><span class="status ' . $status->color . '"></span> ' . $status->name . '</span>';
        }

        return '';
    }

    /**
     * Paid status represented as HTML
     */
    public function getPaidStatusHtml(): string
    {
        return match ($this->getPaidStatus()) {
            self::PAID_STATUS_OVERPAID => '<span class="commerceStatusLabel"><span class="status blue"></span> ' . Craft::t('commerce', 'Overpaid') . '</span>',
            self::PAID_STATUS_PAID => '<span class="commerceStatusLabel"><span class="status green"></span> ' . Craft::t('commerce', 'Paid') . '</span>',
            self::PAID_STATUS_PARTIAL => '<span class="commerceStatusLabel"><span class="status orange"></span> ' . Craft::t('commerce', 'Partial') . '</span>',
            self::PAID_STATUS_UNPAID => '<span class="commerceStatusLabel"><span class="status red"></span> ' . Craft::t('commerce', 'Unpaid') . '</span>',
            default => '',
        };
    }

    /**
     * Returns the raw total of the order, which is the total of all line items and adjustments. This number can be negative, so it is not the price of the order.
     *
     * @see Order::getTotalPrice() The actual total price of the order.
     *
     */
    public function getTotal(): float
    {
        return Currency::round($this->getItemSubtotal() + $this->getAdjustmentsTotal());
    }

    /**
     * Get the total price of the order, whose minimum value is enforced by the configured {@link Settings::minimumTotalPriceStrategy strategy set for minimum total price}.
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

    public function getItemTotal(): float
    {
        $total = 0;

        foreach ($this->getLineItems() as $lineItem) {
            $total += $lineItem->getTotal();
        }

        return $total;
    }

    /**
     * @since 3.4
     */
    public function hasShippableItems(): bool
    {
        foreach ($this->getLineItems() as $item) {
            if ($item->getIsShippable()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the difference between the order amount and amount paid.
     *
     *
     */
    public function getOutstandingBalance(): float
    {
        $totalPaid = Currency::round($this->getTotalPaid());
        $totalPrice = $this->getTotalPrice(); // Already rounded

        return $totalPrice - $totalPaid;
    }

    public function hasOutstandingBalance(): bool
    {
        return $this->getOutstandingBalance() > 0;
    }

    /**
     * Returns the total `purchase` and `captured` transactions belonging to this order.
     */
    public function getTotalPaid(): float
    {
        if (!$this->id) {
            return 0;
        }

        if ($this->_transactions === null) {
            $this->_transactions = Plugin::getInstance()->getTransactions()->getAllTransactionsByOrderId($this->id);
        }

        $paidTransactions = ArrayHelper::where($this->_transactions, static function(Transaction $transaction) {
            return $transaction->status == TransactionRecord::STATUS_SUCCESS && ($transaction->type == TransactionRecord::TYPE_PURCHASE || $transaction->type == TransactionRecord::TYPE_CAPTURE);
        });

        $refundedTransactions = ArrayHelper::where($this->_transactions, static function(Transaction $transaction) {
            return $transaction->status == TransactionRecord::STATUS_SUCCESS && $transaction->type == TransactionRecord::TYPE_REFUND;
        });

        $paid = array_sum(ArrayHelper::getColumn($paidTransactions, 'amount', false));
        $refunded = array_sum(ArrayHelper::getColumn($refundedTransactions, 'amount', false));

        return $paid - $refunded;
    }

    /**
     * @return float
     */
    public function getTotalAuthorized()
    {
        if (!$this->id) {
            return 0;
        }

        $customerized = 0;
        $captured = 0;

        if ($this->_transactions === null) {
            $this->_transactions = Plugin::getInstance()->getTransactions()->getAllTransactionsByOrderId($this->id);
        }

        foreach ($this->_transactions as $transaction) {
            $isSuccess = ($transaction->status == TransactionRecord::STATUS_SUCCESS);
            $isAuth = ($transaction->type == TransactionRecord::TYPE_AUTHORIZE);
            $isCapture = ($transaction->type == TransactionRecord::TYPE_CAPTURE);

            if (!$isSuccess) {
                continue;
            }

            if ($isAuth) {
                $customerized += $transaction->amount;
                continue;
            }

            if ($isCapture) {
                $captured += $transaction->amount;
            }
        }

        return $customerized - $captured;
    }

    /**
     * Returns whether this order is the user's current active cart.
     *
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
     */
    public function getIsEmpty(): bool
    {
        return $this->getTotalQty() == 0;
    }

    /**
     * @noinspection PhpUnused
     */
    public function hasLineItems(): bool
    {
        return (bool)$this->getLineItems();
    }

    /**
     * Returns total number of items.
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
        if (!isset($this->_lineItems)) {
            $lineItems = $this->id ? Plugin::getInstance()->getLineItems()->getAllLineItemsByOrderId($this->id) : [];
            foreach ($lineItems as $lineItem) {
                $lineItem->setOrder($this);
            }
            $this->_lineItems = $lineItems;
        }

        return array_filter($this->_lineItems);
    }

    /**
     * @param LineItem[] $lineItems
     */
    public function setLineItems(array $lineItems): void
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
                $this->_lineItems = [array_shift($lineItems)];
            }
        } else {
            $this->_lineItems = $lineItems;
        }
    }

    public function _getAdjustmentsTotalByType(array|string $types, bool $included = false): float|int
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

    public function getTotalTax(): float
    {
        return $this->_getAdjustmentsTotalByType('tax');
    }

    public function getTotalTaxIncluded(): float
    {
        return $this->_getAdjustmentsTotalByType('tax', true);
    }

    public function getTotalDiscount(): float
    {
        return $this->_getAdjustmentsTotalByType('discount');
    }

    public function getTotalShippingCost(): float
    {
        return $this->_getAdjustmentsTotalByType('shipping');
    }

    /**
     * @noinspection PhpUnused
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
     * @noinspection PhpUnused
     */
    public function getAdjustmentSubtotal(): float
    {
        $value = 0;
        foreach ($this->getAdjustments() as $adjustment) {
            if (!$adjustment->included) {
                $value += $adjustment->amount;
            }
        }

        return (float)$value;
    }

    /**
     * @return OrderAdjustment[]|null
     * @throws InvalidConfigException
     */
    public function getAdjustments(): ?array
    {
        if (isset($this->_orderAdjustments)) {
            return $this->_orderAdjustments;
        }

        if ($this->id) {
            $this->setAdjustments(Plugin::getInstance()->getOrderAdjustments()->getAllOrderAdjustmentsByOrderId($this->id));
        }

        return $this->_orderAdjustments ?? [];
    }

    /**
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
    public function setAdjustments(array $adjustments): void
    {
        $this->_orderAdjustments = $adjustments;
    }

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
     * * Get the shipping address on the order.
     */
    public function getShippingAddress(): ?AddressElement
    {
        if (!isset($this->_shippingAddress) && $this->shippingAddressId) {
            $this->_shippingAddress = AddressElement::find()->id($this->shippingAddressId)->ownerId($this->id)->one();
        }

        return $this->_shippingAddress;
    }

    /**
     * Set the shipping address on the order.
     *
     * @param AddressElement|array|null $address
     */
    public function setShippingAddress(mixed $address): void
    {
        if ($address === null) {
            $this->shippingAddressId = null;
            $this->_shippingAddress = null;
            return;
        }

        if (is_array($address)) {
            $addressElement = $this->_shippingAddress ?: new AddressElement();
            $addressElement->setAttributes($address);
            $addressElement->ownerId = $this->id;
            $address = $addressElement;
        }

        if (!$address instanceof AddressElement) {
            throw new InvalidArgumentException('Shipping address supplied is not an Address Element');
        }

        // Ensure that address can only belong to this order
        if ($address->ownerId != $this->id) {
            throw new InvalidArgumentException('Can not set a shipping address on the order that is is not owned by the order.');
        }

        $this->shippingAddressId = $address->id;
        $this->_shippingAddress = $address;
    }

    /**
     * @since 3.1
     */
    public function removeShippingAddress(): void
    {
        $this->shippingAddressId = null;
        $this->_shippingAddress = null;
    }

    /**
     * @since 2.2
     */
    public function getEstimatedShippingAddress(): ?AddressElement
    {
        if (!isset($this->_estimatedShippingAddress) && $this->estimatedShippingAddressId) {
            $this->_estimatedShippingAddress = AddressElement::find()->owner($this)->id($this->estimatedShippingAddressId)->one();
        }

        return $this->_estimatedShippingAddress;
    }

    /**
     * @since 2.2
     */
    public function setEstimatedShippingAddress(AddressElement|array $address): void
    {
        if (!$address instanceof AddressElement) {
            $addressElement = new AddressElement();
            $addressElement->setAttributes($address);
            $address = $addressElement;
        }

        $this->estimatedShippingAddressId = $address->id;
        $this->_estimatedShippingAddress = $address;
    }

    /**
     * @since 3.1
     */
    public function removeEstimatedShippingAddress(): void
    {
        $this->estimatedShippingAddressId = null;
        $this->_estimatedShippingAddress = null;
    }

    /**
     * Get the billing address on the order.
     */
    public function getBillingAddress(): ?AddressElement
    {
        if (!isset($this->_billingAddress) && $this->billingAddressId) {
            $this->_billingAddress = AddressElement::find()->id($this->billingAddressId)->ownerId($this->id)->one();
        }

        return $this->_billingAddress;
    }

    /**
     * Set the billing address on the order.
     *
     * @param AddressElement|array|null $address
     */
    public function setBillingAddress(mixed $address): void
    {
        if ($address === null) {
            $this->billingAddressId = null;
            $this->_billingAddress = null;
            return;
        }

        if (is_array($address)) {
            $addressElement = $this->_billingAddress ?: new AddressElement();
            $addressElement->setAttributes($address);
            $addressElement->ownerId = $this->id;
            $address = $addressElement;
        }

        if (!$address instanceof AddressElement) {
            throw new InvalidArgumentException('Billing address supplied is not an Address Element');
        }

        // Ensure that address can only belong to this order
        if ($address->ownerId !== $this->id) {
            throw new InvalidArgumentException('Can not set a billing address on the order that is is not owned by the order.');
        }

        $address->ownerId = $this->id;
        $this->billingAddressId = $address->id;
        $this->_billingAddress = $address;
    }

    /**
     * @since 3.1
     */
    public function removeBillingAddress(): void
    {
        $this->billingAddressId = null;
        $this->_billingAddress = null;
    }

    /**
     * @since 2.2
     */
    public function getEstimatedBillingAddress(): ?AddressElement
    {
        if (!isset($this->_estimatedBillingAddress) && $this->estimatedBillingAddressId) {
            $this->_estimatedBillingAddress = AddressElement::find()->owner($this)->id($this->estimatedBillingAddressId)->one();
        }

        return $this->_estimatedBillingAddress;
    }

    /**
     * @since 2.2
     */
    public function setEstimatedBillingAddress(AddressElement|array $address): void
    {
        if (!$address instanceof AddressElement) {
            $addressElement = new AddressElement();
            $addressElement->setAttributes($address);
        }

        $this->estimatedBillingAddressId = $address->id;
        $this->_estimatedBillingAddress = $address;
    }

    /**
     * @since 3.1
     */
    public function removeEstimatedBillingAddress(): void
    {
        $this->estimatedBillingAddressId = null;
        $this->_estimatedBillingAddress = null;
    }

    public function getShippingMethod(): ?ShippingMethod
    {
        return Plugin::getInstance()->getShippingMethods()->getShippingMethodByHandle((string)$this->shippingMethodHandle);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function getGateway(): ?GatewayInterface
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
            if ($this->gatewayId) {
                $gateway = Plugin::getInstance()->getGateways()->getGatewayById((int)$this->gatewayId);
            }
        }

        return $gateway;
    }

    /**
     * Returns the current payment currency, and defaults to the primary currency if not set.
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
    public function setPaymentCurrency(
        string $value
    ): void {
        $this->_paymentCurrency = $value;
    }

    /**
     * Returns the order's selected payment source if any.
     *
     * @throws InvalidConfigException if the payment source is being set by a guest customer.
     * @throws InvalidArgumentException if the order is set to an invalid payment source.
     */
    public function getPaymentSource(): ?PaymentSource
    {
        if ($this->paymentSourceId === null) {
            return null;
        }

        if (($user = $this->getCustomer()) === null) {
            throw new InvalidConfigException('Guest customers can not set a payment source.');
        }

        if (($paymentSource = Plugin::getInstance()->getPaymentSources()->getPaymentSourceByIdAndUserId($this->paymentSourceId, $user->id)) === null) {
            throw new InvalidArgumentException("Invalid payment source ID: {$this->paymentSourceId}");
        }

        return $paymentSource;
    }

    /**
     * Sets the order's selected payment source
     */
    public function setPaymentSource(
        ?PaymentSource $paymentSource
    ): void {
        if (!$paymentSource instanceof PaymentSource && $paymentSource !== null) {
            throw new InvalidArgumentException('Only a PaymentSource or null are accepted params');
        }

        // Setting the payment source to null clears it
        if ($paymentSource === null) {
            $this->paymentSourceId = null;
        }

        if ($paymentSource instanceof PaymentSource) {
            $user = $this->getCustomer();
            if ($user && $paymentSource->getUser()->id != $user->id) {
                throw new InvalidArgumentException('PaymentSource is not owned by the user of the order.');
            }

            $this->paymentSourceId = $paymentSource->id;
            $this->gatewayId = null;
        }
    }

    /**
     * Sets the order's selected gateway id.
     */
    public function setGatewayId(
        int $gatewayId
    ): void {
        $this->gatewayId = $gatewayId;
        $this->paymentSourceId = null;
    }

    /**
     * @return OrderHistory[]
     */
    public function getHistories(): array
    {
        return Plugin::getInstance()->getOrderHistories()->getAllOrderHistoriesByOrderId($this->id);
    }

    /**
     * Set transactions on the order. Set to null to clear cache and force next getTransactions() call to get the latest transactions.
     *
     * @param Transaction[]|null $transactions
     * @since 3.2.0
     */
    public function setTransactions(?array $transactions): void
    {
        $this->_transactions = $transactions;
    }

    /**
     * @return Transaction[]
     */
    public function getTransactions(): array
    {
        if (!$this->id) {
            $this->_transactions = [];
        }

        if ($this->_transactions === null) {
            $this->_transactions = Plugin::getInstance()->getTransactions()->getAllTransactionsByOrderId($this->id);
        }

        return $this->_transactions;
    }

    /**
     * @noinspection PhpUnused
     */
    public function getLastTransaction(): ?Transaction
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
     * @throws InvalidConfigException
     */
    public function getOrderStatus(): ?OrderStatus
    {
        return $this->orderStatusId !== null ? Plugin::getInstance()->getOrderStatuses()->getOrderStatusById($this->orderStatusId) : null;
    }

    /**
     * Get the site for the order.
     *
     * @since 3.2.9
     */
    public function getOrderSite(): ?Site
    {
        if (!$this->orderSiteId) {
            return null;
        }

        return Craft::$app->getSites()->getSiteById($this->orderSiteId);
    }

    /**
     * @inheritdoc
     */
    public function getMetadata(): array
    {
        $metadata = [];

        if ($this->isCompleted) {
            $metadata[Craft::t('commerce', 'Reference')] = $this->reference;
            $metadata[Craft::t('commerce', 'Date Ordered')] = Craft::$app->getFormatter()->asDatetime($this->dateOrdered, 'short');
        }

        $metadata[Craft::t('commerce', 'Coupon Code')] = $this->couponCode;

        $orderSite = $this->getOrderSite();
        $metadata[Craft::t('commerce', 'Order Site')] = $orderSite->getName() ?? '';

        $shippingMethod = $this->getShippingMethod();
        $metadata[Craft::t('commerce', 'Shipping Method')] = $shippingMethod->getName() ?? '';

        $metadata[Craft::t('app', 'ID')] = $this->id;
        $metadata[Craft::t('commerce', 'Short Number')] = $this->getShortNumber();
        $metadata[Craft::t('commerce', 'Paid Status')] = $this->getPaidStatusHtml();
        $metadata[Craft::t('commerce', 'Total Price')] = $this->totalPriceAsCurrency;
        $metadata[Craft::t('commerce', 'Paid Amount')] = $this->totalPaidAsCurrency;
        $metadata[Craft::t('commerce', 'Origin')] = $this->origin;

        return array_merge($metadata, parent::getMetadata());
    }

    /**
     * Updates the adjustments, including deleting the old ones.
     *
     * @throws Exception
     * @throws Throwable
     * @throws StaleObjectException
     */
    private
    function _saveAdjustments(): void
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
    }


    /**
     * @throws StaleObjectException
     * @throws Throwable
     */
    private
    function _saveNotices(): void
    {
        $previousNoticeIds = (new Query())
            ->select(['id'])
            ->from([Table::ORDERNOTICES])
            ->where(['orderId' => $this->id])
            ->column();

        $currentNoticeIds = [];

        // We are never updating a notice, just adding it or keeping it.
        foreach ($this->getNotices() as $notice) {
            if ($notice->id === null) {
                $noticeRecord = new OrderNoticeRecord();
                $noticeRecord->orderId = $notice->orderId;
                $noticeRecord->type = $notice->type;
                $noticeRecord->attribute = $notice->attribute;
                $noticeRecord->message = $notice->message;
                if ($noticeRecord->save(false)) {
                    $notice->id = $noticeRecord->id;
                }
            }

            $currentNoticeIds[] = $notice->id;
        }

        // Delete any notices that are no longer on the order
        if ($deletableNoticeIds = array_diff($previousNoticeIds, $currentNoticeIds)) {
            OrderNoticeRecord::deleteAll(['id' => $deletableNoticeIds]);
        }
    }

    /**
     * Updates the line items, including deleting the old ones.
     *
     * @throws Throwable
     */
    private
    function _saveLineItems(): void
    {
        // Line items that are currently in the DB
        $previousLineItems = LineItemRecord::find()
            ->where(['orderId' => $this->id])
            ->all();

        $currentLineItemIds = [];

        // Determine the line items that will be saved
        foreach ($this->getLineItems() as $lineItem) {
            // If the ID is null that's ok, it's a new line item and will be saved anyway
            $currentLineItemIds[] = $lineItem->id;
        }

        // Delete any line items that no longer will be saved on this order.
        foreach ($previousLineItems as $previousLineItem) {
            if (!in_array($previousLineItem->id, $currentLineItemIds, false)) {

                $lineItem = Plugin::getInstance()->getLineItems()->getLineItemById($previousLineItem->id);
                $previousLineItem->delete();

                if ($this->hasEventHandlers(self::EVENT_AFTER_APPLY_REMOVE_LINE_ITEM)) {
                    $this->trigger(self::EVENT_AFTER_APPLY_REMOVE_LINE_ITEM, new LineItemEvent([
                        'lineItem' => $lineItem,
                    ]));
                }
            }
        }

        // Save the line items last, as we know that any possible duplicates are already removed.
        // We also need to re-save any adjustments that didn't have an line item ID for a line item if it's new.
        foreach ($this->getLineItems() as $lineItem) {

            $originalId = $lineItem->id;
            $lineItem->setOrder($this); // just in case.

            // Don't run validation as validation of the line item should happen before saving the order
            Plugin::getInstance()->getLineItems()->saveLineItem($lineItem, false);

            // Is this a new line item?
            if ($originalId === null) {
                // Raising the 'afterAddLineItemToOrder' event
                if ($this->hasEventHandlers(self::EVENT_AFTER_APPLY_ADD_LINE_ITEM)) {
                    $this->trigger(self::EVENT_AFTER_APPLY_ADD_LINE_ITEM, new LineItemEvent([
                        'lineItem' => $lineItem,
                        'isNew' => true,
                    ]));
                }
            }

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
