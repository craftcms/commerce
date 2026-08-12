<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Elements;

use CommerceGuys\Addressing\AddressInterface;
use Craft;
use craft\base\NameTrait;
use craft\commerce\base\Purchasable;
use craft\commerce\elements\actions\CopyLoadCartUrl;
use craft\commerce\elements\actions\DownloadOrderPdfAction;
use craft\commerce\elements\actions\UpdateOrderStatus;
use craft\commerce\elements\conditions\orders\OrderCondition;
use craft\commerce\elements\conditions\orders\OrderStatusConditionRule;
use craft\commerce\errors\LineItemNotFoundException;
use craft\commerce\errors\OrderAdjustmentNotFoundException;
use craft\commerce\exports\Expanded;
use craft\commerce\Plugin;
use craft\commerce\records\OrderNotice as OrderNoticeRecord;
use craft\commerce\records\Transaction as TransactionRecord;
use craft\errors\MutexException;
use craft\helpers\ArrayHelper;
use craft\helpers\StringHelper;
use CraftCms\Cms\Address\Elements\Address as AddressElement;
use CraftCms\Cms\Component\ComponentHelper;
use CraftCms\Cms\Cp\Html\StatusHtml;
use CraftCms\Cms\Cp\RequestedSite;
use CraftCms\Cms\Element\Actions\Delete;
use CraftCms\Cms\Element\Actions\Restore;
use CraftCms\Cms\Element\Conditions\Contracts\ElementConditionInterface;
use CraftCms\Cms\Element\Element;
use CraftCms\Cms\Element\Exporters\Expanded as CraftExpanded;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Cms\Element\Validation\ElementRules;
use CraftCms\Cms\Field\BaseRelationField;
use CraftCms\Cms\Field\Contracts\FieldInterface;
use CraftCms\Cms\FieldLayout\FieldLayout;
use CraftCms\Cms\Site\Data\Site;
use CraftCms\Cms\Support\Facades\Addresses;
use CraftCms\Cms\Support\Facades\Conditions;
use CraftCms\Cms\Support\Facades\Deprecator;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\ElementActions;
use CraftCms\Cms\Support\Facades\Fields;
use CraftCms\Cms\Support\Facades\I18N;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\Support\Facades\Users;
use CraftCms\Cms\Support\Html;
use CraftCms\Cms\Support\Query;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\Translation\Locale;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Helpers\Currency;
use CraftCms\Commerce\Helpers\Order as OrderHelper;
use CraftCms\Commerce\Inventory\Enums\ContainsPurchasablesMatch;
use CraftCms\Commerce\Order\Enums\OrderNoticeType;
use CraftCms\Commerce\Order\Events\AddLineItemEvent;
use CraftCms\Commerce\Order\Events\LineItemEvent;
use CraftCms\Commerce\Order\Events\OrderLineItemsRefreshEvent;
use CraftCms\Commerce\Order\Events\OrderNoticeEvent;
use CraftCms\Commerce\Order\Exceptions\OrderStatusException;
use CraftCms\Commerce\Order\LineItem\Data\LineItem;
use CraftCms\Commerce\Order\Models\Order as OrderRecord;
use CraftCms\Commerce\Order\Queries\OrderQuery;
use CraftCms\Commerce\Order\Models\OrderAdjustment;
use CraftCms\Commerce\Order\Models\OrderHistory;
use CraftCms\Commerce\Order\Models\OrderNotice;
use CraftCms\Commerce\Order\Models\OrderStatus;
use CraftCms\Commerce\Order\Validation\OrderRules;
use CraftCms\Commerce\Payment\Gateway\Contracts\GatewayInterface;
use CraftCms\Commerce\Payment\Models\PaymentSource;
use CraftCms\Commerce\Payment\Models\Transaction;
use CraftCms\Commerce\Purchasable\Contracts\PurchasableInterface;
use CraftCms\Commerce\Purchasable\Elements\Purchasable as NewPurchasable;
use CraftCms\Commerce\Services\Carts;
use CraftCms\Commerce\Services\Currencies;
use CraftCms\Commerce\Services\Customers;
use CraftCms\Commerce\Services\Discounts;
use CraftCms\Commerce\Services\Gateways;
use CraftCms\Commerce\Services\Inventory;
use CraftCms\Commerce\Services\LineItems;
use CraftCms\Commerce\Services\OrderAdjustments;
use CraftCms\Commerce\Services\OrderHistories;
use CraftCms\Commerce\Services\OrderStatuses;
use CraftCms\Commerce\Services\PaymentCurrencies;
use CraftCms\Commerce\Services\PaymentSources;
use CraftCms\Commerce\Services\Pdfs;
use CraftCms\Commerce\Services\Purchasables;
use CraftCms\Commerce\Services\ShippingMethods;
use CraftCms\Commerce\Services\Stores;
use CraftCms\Commerce\Services\Transactions;
use CraftCms\Commerce\Services\Vat;
use CraftCms\Commerce\Shipping\Contracts\ShippingMethodInterface;
use CraftCms\Commerce\Shipping\Models\ShippingMethod;
use CraftCms\Commerce\Shipping\Models\ShippingMethodOption;
use CraftCms\Commerce\Store\Concerns\StoreTrait;
use CraftCms\Commerce\Store\Contracts\HasStoreInterface;
use CraftCms\Commerce\Store\Models\Store;
use CraftCms\RulesetValidation\Attributes\Ruleset;
use DateTime;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Validator;
use Money\Teller;
use Override;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use Throwable;
use yii\base\Exception;
use yii\base\InvalidArgumentException;
use yii\base\InvalidCallException;
use yii\base\InvalidConfigException;

use function CraftCms\Cms\currentUser;
use function CraftCms\Cms\renderObjectTemplate;
use function CraftCms\Cms\t;

/**
 * Order or Cart element.
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
 * @property int|null $customerId The order customer ID
 * @property-read bool $activeCart Is the current order the same as the active cart
 * @property-read User|null $customer
 * @property-read GatewayInterface|null $gateway
 * @property-read OrderStatus|null $orderStatus
 * @property-read float $outstandingBalance The balance amount to be paid on the Order
 * @property-read User|null $user
 * @property-read OrderAdjustment[] $orderAdjustments
 * @property-read string $pdfUrl the URL to the order's PDF invoice
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
 * @property-read string $paidStatus the order's paid status
 * @property-read string $paidStatusHtml the order's paid status as HTML
 * @property-read string $shortNumber
 * @property-read float $totalPaid the total `purchase` and `captured` transactions belonging to this order
 * @property-read float $total
 * @property-read float $totalPrice
 * @property-read int $totalPromotionalAmount the total sale amount
 * @property-read int $totalQty the total number of items
 * @property-read int $totalWeight
 * @property-read string $orderStatusHtml
 * @property-read string $customerLinkHtml
 * @property-read Site|null $orderSite
 * @property null|array|AddressElement $estimatedBillingAddress
 * @property float $totalDiscount
 * @property null|array|AddressElement $estimatedShippingAddress
 * @property float $totalTaxIncluded
 * @property float $totalTax
 * @property float $totalShippingCost
 * @property-read ShippingMethodOption[] $availableShippingMethodOptions
 * @property-read float|int $totalAuthorized
 * @property float $paymentAmount
 * @property-read null|string $loadCartUrl
 * @property-read array $metadata
 * @property-read int $totalCommittedStock
 * @property-read Teller $teller
 * @property-read Transaction[] $transactions
 */
#[Ruleset(OrderRules::class)]
class Order extends Element implements HasStoreInterface
{
    use StoreTrait;

    public const PAID_STATUS_OVERPAID = 'overPaid';

    public const PAID_STATUS_PAID = 'paid';

    public const PAID_STATUS_PARTIAL = 'partial';

    public const PAID_STATUS_UNPAID = 'unpaid';

    /**
     * Recalculates line items, populates from purchasables, and regenerates adjustments.
     */
    public const RECALCULATION_MODE_ALL = 'all';

    /**
     * Recalculates adjustments only; does not recalculate line items or populate from purchasables.
     */
    public const RECALCULATION_MODE_ADJUSTMENTS_ONLY = 'adjustmentsOnly';

    /**
     * Does not recalculate anything on the order.
     */
    public const RECALCULATION_MODE_NONE = 'none';

    public const ORIGIN_WEB = 'web';

    public const ORIGIN_CP = 'cp';

    public const ORIGIN_REMOTE = 'remote';

    /**
     * @event \yii\base\Event The event that is triggered before a new line item has been added to the order.
     */
    public const EVENT_BEFORE_ADD_LINE_ITEM = 'beforeAddLineItemToOrder';

    /**
     * @event \yii\base\Event The event that is triggered after a line item has been applied to an order.
     */
    public const EVENT_AFTER_APPLY_ADD_LINE_ITEM = 'afterApplyAddLineItemToOrder';

    /**
     * @event \yii\base\Event The event that is triggered after a line item has been added to an order.
     */
    public const EVENT_AFTER_ADD_LINE_ITEM = 'afterAddLineItemToOrder';

    /**
     * @event \yii\base\Event The event that is triggered after a line item has been removed from an order.
     */
    public const EVENT_AFTER_REMOVE_LINE_ITEM = 'afterRemoveLineItemFromOrder';

    /**
     * @event \yii\base\Event The event that is triggered after a line item removal has been applied to an order.
     */
    public const EVENT_AFTER_APPLY_REMOVE_LINE_ITEM = 'afterApplyRemoveLineItemFromOrder';

    /**
     * @event \yii\base\Event The event that is triggered before an order is completed.
     */
    public const EVENT_BEFORE_COMPLETE_ORDER = 'beforeCompleteOrder';

    /**
     * @event \yii\base\Event The event that is triggered after an order is completed.
     */
    public const EVENT_AFTER_COMPLETE_ORDER = 'afterCompleteOrder';

    /**
     * @event \yii\base\Event The event that is triggered after an order is paid and completed.
     */
    public const EVENT_AFTER_ORDER_PAID = 'afterOrderPaid';

    /**
     * @event \yii\base\Event The event that is triggered after an order is authorized in full and completed.
     */
    public const EVENT_AFTER_ORDER_AUTHORIZED = 'afterOrderAuthorized';

    /**
     * @event \yii\base\Event The event that is triggered before a notice has been added to the order.
     */
    public const EVENT_BEFORE_APPLY_ADD_NOTICE = 'beforeApplyAddNoticeToOrder';

    /**
     * @event \yii\base\Event The event that is triggered before line items are refreshed during recalculation of an order.
     */
    public const EVENT_BEFORE_LINE_ITEMS_REFRESHED = 'beforeLineItemsRefreshed';

    /**
     * @event \yii\base\Event The event that is triggered after line items are refreshed during recalculation of an order.
     */
    public const EVENT_AFTER_LINE_ITEMS_REFRESHED = 'afterLineItemsRefreshed';

    /**
     * The unique number (hash) generated for the order when it was first created.
     */
    public ?string $number = null;

    /**
     * The reference number generated once the order was completed. While the order is a cart, this is null.
     */
    public ?string $reference = null;

    /**
     * The currently applied coupon code.
     */
    public ?string $couponCode = null;

    /**
     * Is this order completed (no longer a cart).
     */
    public bool $isCompleted = false;

    public ?DateTime $dateOrdered = null;

    public ?DateTime $datePaid = null;

    public ?DateTime $dateFirstPaid = null;

    /**
     * This may be the same date as datePaid if the order was paid immediately.
     */
    public ?DateTime $dateAuthorized = null;

    /**
     * The currency of the order (ISO code).
     */
    public ?string $currency = null;

    /**
     * The current gateway ID to identify the gateway the order should use when accepting payments.
     * If the `paymentSourceId` is set on this order, this `gatewayId` will be that belonging to the payment source.
     */
    public ?int $gatewayId = null;

    /**
     * The last IP address of the user building the order before it was marked as complete.
     */
    public ?string $lastIp = null;

    /**
     * The current message set on the order when having its order status being changed.
     */
    public ?string $message = null;

    /**
     * The current URL the order should return to after successful payment. Stored on the order
     * as we may be redirected off-site for payments.
     */
    public ?string $returnUrl = null;

    /**
     * The current URL the order should return to if the customer cancels payment off-site.
     */
    public ?string $cancelUrl = null;

    /**
     * The current order status ID. Null if the order is not complete and is still a cart.
     */
    public ?int $orderStatusId = null;

    /**
     * The language the cart was created in.
     */
    public ?string $orderLanguage = null;

    /**
     * The store the order was created in.
     */
    public ?int $storeId = null;

    /**
     * The site the order was created in.
     */
    public ?int $orderSiteId = null;

    /**
     * The origin of the order when it was first created. Values can be 'web', 'cp', or 'remote'.
     */
    public ?string $origin = null;

    /**
     * The email address that was on the cart when the order was completed. Only stored for historic data.
     */
    public ?string $orderCompletedEmail = null;

    public ?int $billingAddressId = null;

    public ?int $shippingAddressId = null;

    /**
     * Whether the shipping address should be made the primary address of the order's customer.
     * This is persisted while the order is a cart, and is only used during the update cart request
     * or on order completion when new addresses are being saved.
     *
     * @see \CraftCms\Commerce\Services\Customers::_saveAddressesFromOrder()
     */
    public bool $makePrimaryShippingAddress = false;

    /**
     * Whether the billing address should be made the primary address of the order's customer.
     *
     * @see \CraftCms\Commerce\Services\Customers::_saveAddressesFromOrder()
     */
    public bool $makePrimaryBillingAddress = false;

    /**
     * Whether the shipping address should be the same address as the order's billing address.
     * Not persisted on the order; only used during the update order request. Can not be set to
     * `true` at the same time as `billingSameAsShipping`, or an error will be raised.
     */
    public bool $shippingSameAsBilling = false;

    /**
     * Whether the billing address should be the same address as the order's shipping address.
     * Not persisted on the order; only used during the update order request. Can not be set to
     * `true` at the same time as `shippingSameAsBilling`, or an error will be raised.
     */
    public bool $billingSameAsShipping = false;

    public ?int $estimatedBillingAddressId = null;

    public ?int $estimatedShippingAddressId = null;

    /**
     * The billing address ID that was selected from the customer's address book, which populated
     * the billing address on the order.
     */
    public ?int $sourceBillingAddressId = null;

    /**
     * The shipping address ID that was selected from the customer's address book, which populated
     * the shipping address on the order.
     */
    public ?int $sourceShippingAddressId = null;

    /**
     * Whether the estimated billing address should be set to the same address as the estimated shipping address.
     */
    public bool $estimatedBillingSameAsShipping = false;

    public ?string $shippingMethodHandle = '';

    public ?string $shippingMethodName = null;

    private ?int $_customerId = null;

    private bool $_customerDeleted = false;

    /**
     * Whether the email address on the order should be used to register as a user account when the
     * order is complete.
     */
    public bool $registerUserOnOrderComplete = false;

    /**
     * Whether the billing address on the order should be saved to the customer's address book when
     * the order is complete.
     */
    public bool $saveBillingAddressOnOrderComplete = false;

    /**
     * Whether the shipping address on the order should be saved to the customer's address book when
     * the order is complete.
     */
    public bool $saveShippingAddressOnOrderComplete = false;

    /**
     * The current payment source that should be used to make payments on the order. If this is set,
     * the `gatewayId` will also be set to the related gateway.
     */
    public ?int $paymentSourceId = null;

    public ?float $storedTotalPrice = null;

    public ?float $storedTotal = null;

    public ?float $storedTotalPaid = null;

    public ?float $storedItemTotal = null;

    public ?float $storedItemSubtotal = null;

    public ?float $storedTotalShippingCost = null;

    public ?float $storedTotalDiscount = null;

    public ?float $storedTotalTax = null;

    public ?float $storedTotalTaxIncluded = null;

    public ?int $storedTotalQty = null;

    /**
     * @see Order::setRecalculationMode() To set the current recalculation mode
     * @see Order::getRecalculationMode() To get the current recalculation mode
     */
    private ?string $_recalculationMode = null;

    private ?AddressElement $_shippingAddress = null;

    private ?AddressElement $_billingAddress = null;

    private ?AddressElement $_estimatedShippingAddress = null;

    private ?AddressElement $_estimatedBillingAddress = null;

    /**
     * @var LineItem[]
     * @see Order::setLineItems() To set the order line items
     * @see Order::getLineItems() To get the order line items
     */
    private array $_lineItems;

    private array $_deletingLineItems = [];

    /**
     * @var OrderAdjustment[]|null
     * @see Order::setAdjustments() To set the order adjustments
     * @see Order::getAdjustments() To get the order adjustments
     */
    private ?array $_orderAdjustments = null;

    /**
     * @see Order::setPaymentCurrency() To set the payment currency
     * @see Order::getPaymentCurrency() To get the payment currency
     */
    private ?string $_paymentCurrency = null;

    /**
     * @var Transaction[]|null
     * @see Order::getTransactions()
     */
    private ?array $_transactions = null;

    /**
     * @see Order::getCustomer()
     * @see Order::setCustomer()
     */
    private User|null|false $_customer = null;

    /**
     * @see Order::setPaymentAmount() To set the order payment amount
     * @see Order::getPaymentAmount() To get the order payment amount
     */
    private ?float $_paymentAmount = null;

    /**
     * Ability to cancel email sending to avoid email even being queued.
     */
    public bool $suppressEmails = false;

    /**
     * @var array
     */
    private array $_notices = [];

    /**
     * The new Element base has no `init()` lifecycle hook (that was Yii2's post-configure
     * callback) — config application now happens entirely in the constructor, so the
     * once-config-is-applied defaulting logic that used to live in `init()` moves here, run
     * after `parent::__construct()` has applied `$config`.
     */
    public function __construct($config = [])
    {
        parent::__construct($config);

        if ($this->orderLanguage === null) {
            $this->orderLanguage = app()->getLocale();
        }

        if ($this->storeId === null) {
            $this->storeId = app(Stores::class)->getCurrentStore()->id;
        }

        if ($this->orderSiteId === null) {
            $storeSites = $this->getStore()->getSites();
            $primarySite = Sites::getPrimarySite();
            // Prefer the Craft primary site if it belongs to this store, otherwise use the first available site
            $this->orderSiteId = $storeSites->firstWhere('id', $primarySite->id)?->id ?? $storeSites->first()->id;
        }

        if ($this->currency === null) {
            $this->currency = $this->getStore()->getCurrency()?->getCode();
        }

        // Better default for carts if the base currency changes (usually only happens in development)
        if (!$this->isCompleted && $this->paymentCurrency && !app(PaymentCurrencies::class)->getPaymentCurrencyByIso($this->paymentCurrency, $this->getStore()->id)) {
            $this->paymentCurrency = app(PaymentCurrencies::class)->getPrimaryPaymentCurrencyIso($this->getStore()->id);
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
    }

    public static function displayName(): string
    {
        return t('Order', category: 'commerce');
    }

    #[Override]
    public static function lowerDisplayName(): string
    {
        return t('order', category: 'commerce');
    }

    #[Override]
    public static function pluralDisplayName(): string
    {
        return t('Orders', category: 'commerce');
    }

    #[Override]
    public static function pluralLowerDisplayName(): string
    {
        return t('orders', category: 'commerce');
    }

    #[Override]
    public function __toString(): string
    {
        return $this->reference ?: $this->getShortNumber();
    }

    #[Override]
    public function canSave(\CraftCms\Cms\User\Elements\User $user): bool
    {
        return parent::canSave($user) || $user->can('commerce-editOrders');
    }

    #[Override]
    public function canView(\CraftCms\Cms\User\Elements\User $user): bool
    {
        return parent::canView($user) || $user->can('commerce-manageOrders');
    }

    #[Override]
    public function canDuplicate(\CraftCms\Cms\User\Elements\User $user): bool
    {
        return false;
    }

    #[Override]
    public function canDelete(\CraftCms\Cms\User\Elements\User $user): bool
    {
        return parent::canDelete($user) || $user->can('commerce-deleteOrders');
    }

    /**
     * The new validation system has no `beforeValidate(): bool` hook — the equivalent
     * pre-validation mutation point is `prepareForValidation()` (Illuminate-style, runs before
     * rules are applied, no return value).
     */
    #[Override]
    public function prepareForValidation(): void
    {
        // Set default gateway if none present and no payment source selected
        if (!$this->gatewayId && !$this->paymentSourceId) {
            $gateways = app(Gateways::class)->getAllCustomerEnabledGateways();
            if ($gateways->isNotEmpty()) {
                $gateway = $gateways->filter(fn(GatewayInterface $g) => $g->availableForUseWithOrder($this))->first();

                if ($gateway) {
                    $this->gatewayId = $gateway->id;
                }
            }
        }

        // If the gateway ID doesn't exist, just drop it.
        if ($this->gatewayId && !$this->getGateway()) {
            $this->gatewayId = null;
        }
    }

    /**
     * Runs the imperative, side-effecting validators that used to be wired up via `defineRules()`'s
     * `[[attributes], 'validateX']` callback syntax. {@see OrderRules} keeps only the handful of
     * plain declarative rules; everything else lives here because it mutates notices/errors on
     * nested models (addresses, line items) using dotted attribute keys, which doesn't map onto
     * Illuminate's rule closures. This is invoked automatically by
     * {@see \CraftCms\Cms\Validation\Ruleset::after()}.
     */
    #[Override]
    public function afterValidate(?Validator $validator = null): void
    {
        $this->validateAddress('billingAddress');
        $this->validateAddress('shippingAddress');
        $this->validateAddressCountry('billingAddress');
        $this->validateAddressCountry('shippingAddress');

        if (!$this->isCompleted) {
            $this->validateAddressReuse('billingAddress');
            $this->validateAddressReuse('shippingAddress');
        }

        if ($this->getStore()->getValidateOrganizationTaxIdAsVatId() && !$this->getStore()->getUseBillingAddressForTax()) {
            $this->validateOrganizationTaxIdAsVatId('shippingAddress');
        }

        if ($this->getStore()->getValidateOrganizationTaxIdAsVatId() && $this->getStore()->getUseBillingAddressForTax()) {
            $this->validateOrganizationTaxIdAsVatId('billingAddress');
        }

        $this->validateLineItems();
        $this->validateCouponCode('couponCode');
        $this->validateGatewayId('gatewayId');
        $this->validatePaymentCurrency('paymentCurrency');
        $this->validatePaymentSourceId('paymentSourceId');
    }

    #[Override]
    public function attributes(): array
    {
        $names = parent::attributes();
        $names[] = 'adjustmentSubtotal';
        $names[] = 'adjustmentsTotal';
        $names[] = 'customer';
        $names[] = 'customerId';
        $names[] = 'customerDeleted';
        $names[] = 'paymentCurrency';
        $names[] = 'paymentAmount';
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
        $names[] = 'totalPromotionalAmount';
        $names[] = 'totalWeight';
        return $names;
    }

    /**
     * The attributes on the order that should be made available as formatted currency.
     */
    public function currencyAttributes(): array
    {
        return [
            'adjustmentSubtotal',
            'adjustmentsTotal',
            'itemSubtotal',
            'itemTotal',
            'outstandingBalance',
            'paymentAmount',
            'totalPaid',
            'total',
            'totalPrice',
            'totalPromotionalAmount',
            'totalTax',
            'totalTaxIncluded',
            'totalShippingCost',
            'totalDiscount',
            'storedTotal',
            'storedTotalPrice',
            'storedTotalPaid',
            'storedItemTotal',
            'storedItemSubtotal',
            'storedTotalShippingCost',
            'storedTotalDiscount',
            'storedTotalTax',
            'storedTotalTaxIncluded',
        ];
    }

    public function getAdjustmentSubtotalAsCurrency(): string
    {
        return $this->_currencyAttributeAsCurrency('adjustmentSubtotal');
    }

    public function getAdjustmentsTotalAsCurrency(): string
    {
        return $this->_currencyAttributeAsCurrency('adjustmentsTotal');
    }

    public function getItemSubtotalAsCurrency(): string
    {
        return $this->_currencyAttributeAsCurrency('itemSubtotal');
    }

    public function getItemTotalAsCurrency(): string
    {
        return $this->_currencyAttributeAsCurrency('itemTotal');
    }

    public function getOutstandingBalanceAsCurrency(): string
    {
        return $this->_currencyAttributeAsCurrency('outstandingBalance');
    }

    public function getPaymentAmountAsCurrency(): string
    {
        return $this->_currencyAttributeAsCurrency('paymentAmount');
    }

    public function getTotalPaidAsCurrency(): string
    {
        return $this->_currencyAttributeAsCurrency('totalPaid');
    }

    public function getTotalAsCurrency(): string
    {
        return $this->_currencyAttributeAsCurrency('total');
    }

    public function getTotalPriceAsCurrency(): string
    {
        return $this->_currencyAttributeAsCurrency('totalPrice');
    }

    public function getTotalPromotionalAmountAsCurrency(): string
    {
        return $this->_currencyAttributeAsCurrency('totalPromotionalAmount');
    }

    /**
     * @deprecated in 5.0.0. Use {@see Order::getTotalPromotionalAmountAsCurrency()} instead.
     */
    #[\Deprecated(message: 'in 5.0.0. Use [[getTotalPromotionalAmountAsCurrency()]] instead.')]
    public function getTotalSaleAmountAsCurrency(): string
    {
        return $this->getTotalPromotionalAmountAsCurrency();
    }

    public function getTotalTaxAsCurrency(): string
    {
        return $this->_currencyAttributeAsCurrency('totalTax');
    }

    public function getTotalTaxIncludedAsCurrency(): string
    {
        return $this->_currencyAttributeAsCurrency('totalTaxIncluded');
    }

    public function getTotalShippingCostAsCurrency(): string
    {
        return $this->_currencyAttributeAsCurrency('totalShippingCost');
    }

    public function getTotalDiscountAsCurrency(): string
    {
        return $this->_currencyAttributeAsCurrency('totalDiscount');
    }

    public function getStoredTotalAsCurrency(): string
    {
        return $this->_currencyAttributeAsCurrency('storedTotal');
    }

    public function getStoredTotalPriceAsCurrency(): string
    {
        return $this->_currencyAttributeAsCurrency('storedTotalPrice');
    }

    public function getStoredTotalPaidAsCurrency(): string
    {
        return $this->_currencyAttributeAsCurrency('storedTotalPaid');
    }

    public function getStoredItemTotalAsCurrency(): string
    {
        return $this->_currencyAttributeAsCurrency('storedItemTotal');
    }

    public function getStoredItemSubtotalAsCurrency(): string
    {
        return $this->_currencyAttributeAsCurrency('storedItemSubtotal');
    }

    public function getStoredTotalShippingCostAsCurrency(): string
    {
        return $this->_currencyAttributeAsCurrency('storedTotalShippingCost');
    }

    public function getStoredTotalDiscountAsCurrency(): string
    {
        return $this->_currencyAttributeAsCurrency('storedTotalDiscount');
    }

    public function getStoredTotalTaxAsCurrency(): string
    {
        return $this->_currencyAttributeAsCurrency('storedTotalTax');
    }

    public function getStoredTotalTaxIncludedAsCurrency(): string
    {
        return $this->_currencyAttributeAsCurrency('storedTotalTaxIncluded');
    }

    /**
     * Mirrors {@see NewPurchasable::_currencyAttributeAsCurrency()}. `CurrencyAttributeBehavior::getDefaultCurrency()`
     * (the legacy behaviour this replaces) unconditionally resolves to the owner's *current* store currency
     * whenever the owner implements `HasStoreInterface` — not the order's own frozen `currency` attribute — so
     * `$this->getStore()->getCurrency()` is the faithful source here, even though it means historic orders are
     * formatted using the store's current currency rather than the currency they were placed in.
     */
    private function _currencyAttributeAsCurrency(string $attribute): string
    {
        $amount = $this->$attribute ?? 0;
        return Currency::formatAsCurrency($amount, $this->getStore()->getCurrency());
    }

    #[Override]
    public function fields(): array
    {
        $fields = parent::fields();

        $datetimeAttributes = ComponentHelper::datetimeAttributes($this);

        // @todo Commerce 6 - remove this and let the parent handle ISO-8601 serialization; update Vue components
        // (OrderMeta.vue, DateOrderedInput.vue) to parse/format dates from ISO-8601 using the JS Intl API instead.
        foreach ($datetimeAttributes as $attribute) {
            $fields[$attribute] = static function($model, $attribute) {
                if (!empty($model->$attribute)) {
                    $formatter = I18N::getFormatter();

                    return [
                        'date' => $formatter->asDate($model->$attribute, Locale::LENGTH_SHORT),
                        'time' => $formatter->asTime($model->$attribute, Locale::LENGTH_SHORT),
                    ];
                }

                return $model->$attribute;
            };
        }

        $fields['email'] = 'email';
        $fields['paidStatusHtml'] = 'paidStatusHtml';
        $fields['customerLinkHtml'] = 'customerLinkHtml';
        $fields['orderStatusHtml'] = 'orderStatusHtml';
        $fields['totalTax'] = 'totalTax';
        $fields['totalTaxIncluded'] = 'totalTaxIncluded';
        $fields['totalShippingCost'] = 'totalShippingCost';
        $fields['totalDiscount'] = 'totalDiscount';

        // @TODO Remove these deprecated `totalSaleAmount` aliases in Commerce 6.0
        $fields['totalSaleAmount'] = 'totalPromotionalAmount';
        $fields['totalSaleAmountAsCurrency'] = 'totalPromotionalAmountAsCurrency';

        return $fields;
    }

    #[Override]
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
        $names[] = 'adminNotices';
        $names[] = 'notices';
        $names[] = 'orderSite';
        $names[] = 'orderStatus';
        $names[] = 'pdfUrl';
        $names[] = 'shippingAddress';
        $names[] = 'shippingMethod';
        $names[] = 'store';
        $names[] = 'totalCommittedStock';
        $names[] = 'transactions';
        return $names;
    }

    public function getTeller(): Teller
    {
        return app(Currencies::class)->getTeller($this->currency);
    }

    /**
     * Automatically set addresses on the order if it's a cart and `autoSetNewCartAddresses` is `true`.
     *
     * @return bool returns true if order is mutated
     */
    public function autoSetAddresses(): bool
    {
        if ($this->isCompleted || !$this->getStore()->getAutoSetNewCartAddresses()) {
            return false;
        }

        $user = $this->getCustomer();
        if (!$user) {
            return false;
        }

        $autoSetOccurred = false;

        if (!$this->_shippingAddress && !$this->shippingAddressId && $primaryShippingAddress = $user->getPrimaryShippingAddress()) {
            $this->sourceShippingAddressId = $primaryShippingAddress->id;
            $shippingAddress = Elements::duplicateElement($primaryShippingAddress, [
                'owner' => $this,
                'primaryOwner' => $this,
            ]);
            $this->setShippingAddress($shippingAddress);
            $autoSetOccurred = true;
        }

        if (!$this->_billingAddress && !$this->billingAddressId && $primaryBillingAddress = $user->getPrimaryBillingAddress()) {
            $this->sourceBillingAddressId = $primaryBillingAddress->id;
            $billingAddress = Elements::duplicateElement($primaryBillingAddress, [
                'owner' => $this,
                'primaryOwner' => $this,
            ]);
            $this->setBillingAddress($billingAddress);
            $autoSetOccurred = true;
        }

        return $autoSetOccurred;
    }

    public function autoSetPaymentSource(): bool
    {
        if ($this->isCompleted || !$this->getStore()->getAutoSetPaymentSource() || $this->paymentSourceId || $this->gatewayId) {
            return false;
        }

        $customer = $this->getCustomer();

        // Only set the payment source if there is a customer set and that is it the current user
        if (!$customer || $customer->id !== currentUser()?->getCraftUserId()) {
            return false;
        }

        $paymentSource = $customer->getPrimaryPaymentSource();
        if (!$paymentSource) {
            return false;
        }

        $this->setPaymentSource($paymentSource);
        return true;
    }

    /**
     * Auto set shipping method based on config settings and available options.
     *
     * @return bool returns true if order is mutated
     */
    public function autoSetShippingMethod(): bool
    {
        if ($this->shippingMethodHandle || $this->isCompleted || !$this->getStore()->getAutoSetCartShippingMethodOption()) {
            return false;
        }

        $availableMethodOptions = $this->getAvailableShippingMethodOptions();
        if (empty($availableMethodOptions)) {
            return false;
        }

        $this->shippingMethodHandle = ArrayHelper::firstKey($availableMethodOptions);

        return true;
    }

    /**
     * Updates the paid status and paid date of the order, and marks as complete if the order is paid or authorized.
     */
    public function updateOrderPaidInformation(): void
    {
        $this->_transactions = null; // clear order's transaction cache

        $paidInFull = !$this->hasOutstandingBalance();
        $authorizedInFull = $this->getTotalAuthorized() >= $this->getTotalPrice();

        $justPaid = $paidInFull && $this->datePaid == null;
        $justAuthorized = $authorizedInFull && $this->dateAuthorized == null;

        $completeTotal = $this->getTeller()->add($this->getTotalAuthorized(), $this->getTotalPaid());
        $canComplete = $this->getTeller()->greaterThan($completeTotal, 0);

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
            $this->datePaid = new DateTime();
        }

        // If it was just paid and this is the first time, set the date first paid to now.
        if ($justPaid && $this->dateFirstPaid === null) {
            $this->dateFirstPaid = new DateTime();
        }

        // If it was just authorized set the date authorized to now.
        if ($justAuthorized) {
            $this->dateAuthorized = new DateTime();
        }

        // Lock for recalculation
        $originalRecalculationMode = $this->getRecalculationMode();
        $this->setRecalculationMode(self::RECALCULATION_MODE_NONE);

        // Saving the order will update the datePaid as set above and also update the paidStatus.
        Elements::saveElement($this, false);

        // If the order is now paid or authorized in full, lets mark it as complete if it has not already been.
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
        $completedInDb = OrderRecord::query()->where('isCompleted', true)->where('id', $this->id)->exists();

        if ($completedInDb) {
            $mutex->release($lockName);
            return true;
        }

        $this->isCompleted = true;
        $this->dateOrdered = new DateTime();

        // Reset estimated address relations
        $this->estimatedShippingAddressId = null;
        $this->estimatedBillingAddressId = null;
        $this->orderCompletedEmail = $this->getEmail();

        $orderStatus = app(OrderStatuses::class)->getDefaultOrderStatusForOrder($this);

        // If the order status returned was overridden by a plugin, use the configured default order status if they give us a bogus one with no ID.
        if ($orderStatus && $orderStatus->id) {
            $this->orderStatusId = $orderStatus->id;
        } else {
            $mutex->release($lockName);
            throw new OrderStatusException('Could not find a valid default order status.');
        }

        if ($this->reference == null) {
            $referenceTemplate = $this->getStore()->getOrderReferenceFormat();

            try {
                // Replaces the legacy `renderSandboxedObjectTemplate()`; object-template rendering is sandboxed by default.
                $baseReference = renderObjectTemplate($referenceTemplate, $this);

                // Check if this reference already exists and append suffix if needed
                $suffix = 0;
                $testReference = $baseReference;

                while (true) {
                    $existingReference = OrderRecord::query()->where('reference', $testReference)->exists();

                    if (!$existingReference) {
                        // Reference is unique, use it
                        $this->reference = $testReference;
                        break;
                    }

                    // Reference exists, increment suffix and try again
                    $suffix++;
                    $testReference = $baseReference . '-' . $suffix;
                }
            } catch (Throwable $exception) {
                $mutex->release($lockName);
                Log::error('Unable to generate order completion reference for order ID: ' . $this->id . ', with format: ' . $referenceTemplate . ', error: ' . $exception->getMessage());
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
        $success = Elements::saveElement($this, false);

        if (!$success) {
            Log::error(t('Could not mark order {number} as complete. Order save failed during order completion with errors: {order}', [
                'number' => $this->number,
                'order' => json_encode($this->errors()->getMessages()),
            ], category: 'commerce'));

            $mutex->release($lockName);
            return false;
        }

        $mutex->release($lockName);

        $this->afterOrderComplete();

        return true;
    }

    /**
     * Called after the order successfully completes.
     */
    public function afterOrderComplete(): void
    {
        // Run order complete handlers directly.
        app(Discounts::class)->orderCompleteHandler($this);
        app(Customers::class)->orderCompleteHandler($this);
        app(Inventory::class)->orderCompleteHandler($this);

        foreach ($this->getLineItems() as $lineItem) {
            app(LineItems::class)->orderCompleteHandler($lineItem, $this);
        }

        // Persist any admin notices added by the handlers above.
        $this->_saveNotices();

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
            $this->trigger(self::EVENT_AFTER_REMOVE_LINE_ITEM, new LineItemEvent(
                lineItem: $lineItem,
            ));
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
            $lineItemEvent = new AddLineItemEvent(lineItem: $lineItem, isNew: $isNew);
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
            $this->trigger(self::EVENT_AFTER_ADD_LINE_ITEM, new LineItemEvent(
                lineItem: $lineItem,
                isNew: !$replaced,
            ));
        }
    }

    /**
     * Returns any line item with that purchasable.
     */
    public function lineItemsByPurchasable(PurchasableInterface $purchasable): Collection
    {
        return collect($this->getLineItems())
            ->filter(fn(LineItem $lineItem) => $lineItem->purchasableId == $purchasable->getId());
    }

    /**
     * Gets the recalculation mode of the order.
     */
    public function getRecalculationMode(): string
    {
        return $this->_recalculationMode ?? self::RECALCULATION_MODE_ALL;
    }

    /**
     * Sets the recalculation mode of the order.
     */
    public function setRecalculationMode(string $value): void
    {
        $this->_recalculationMode = $value;
    }

    /**
     * Regenerates all adjusters and updates line items, depending on the current recalculationMode.
     */
    public function recalculate(): void
    {
        if (!$this->id) {
            throw new InvalidCallException('Do not recalculate an order that has not been saved');
        }

        if ($this->errors()->isNotEmpty()) {
            Log::info(t('Do not call recalculate on the order (Number: {orderNumber}) if errors are present.', ['orderNumber' => $this->number], category: 'commerce'));
            return;
        }

        if ($this->getRecalculationMode() == self::RECALCULATION_MODE_NONE) {
            return;
        }

        if ($this->getRecalculationMode() == self::RECALCULATION_MODE_ALL) {
            // Make sure we set a default shipping method option
            if (!$this->isCompleted && $this->getStore()->getAutoSetCartShippingMethodOption()) {
                $availableMethodOptions = $this->getAvailableShippingMethodOptions();
                if (!$this->shippingMethodHandle || !isset($availableMethodOptions[$this->shippingMethodHandle])) {
                    $this->shippingMethodHandle = ArrayHelper::firstKey($availableMethodOptions);
                }
            }

            if (!$this->shippingMethodHandle) {
                $this->shippingMethodName = null;
            } else {
                $shippingMethod = ArrayHelper::firstWhere($this->getAvailableShippingMethodOptions(), 'handle', $this->shippingMethodHandle);
                if ($shippingMethod) {
                    $this->shippingMethodName = $shippingMethod->getName();
                }
            }

            $recalculateOrder = false;
            if ($this->hasEventHandlers(self::EVENT_BEFORE_LINE_ITEMS_REFRESHED)) {
                $event = new OrderLineItemsRefreshEvent(
                    lineItems: $this->getLineItems(),
                    recalculate: $recalculateOrder,
                );
                $this->trigger(self::EVENT_BEFORE_LINE_ITEMS_REFRESHED, $event);

                $this->setLineItems($event->lineItems);
                $recalculateOrder = $event->recalculate;
            }

            foreach ($this->getLineItems() as $item) {
                $originalSalePrice = $item->getSalePrice();
                $originalSalePriceAsCurrency = $item->salePriceAsCurrency;

                if ($item->refresh()) {
                    if ($originalSalePrice > $item->salePrice) {
                        $message = t('The price of {description} was reduced from {originalSalePriceAsCurrency} to {newSalePriceAsCurrency}', ['originalSalePriceAsCurrency' => $originalSalePriceAsCurrency, 'newSalePriceAsCurrency' => $item->salePriceAsCurrency, 'description' => $item->getDescription()], category: 'commerce');
                        $notice = new OrderNotice([
                            'type' => 'lineItemSalePriceChanged',
                            'attribute' => "lineItems.$item->id.salePrice",
                            'message' => $message,
                        ]);
                        $this->addNotice($notice);
                    }

                    if ($originalSalePrice < $item->salePrice) {
                        $message = t('The price of {description} increased from {originalSalePriceAsCurrency} to {newSalePriceAsCurrency}', ['originalSalePriceAsCurrency' => $originalSalePriceAsCurrency, 'newSalePriceAsCurrency' => $item->salePriceAsCurrency, 'description' => $item->getDescription()], category: 'commerce');
                        $notice = new OrderNotice([
                            'type' => 'lineItemSalePriceChanged',
                            'attribute' => "lineItems.$item->id.salePrice",
                            'message' => $message,
                        ]);
                        $this->addNotice($notice);
                    }
                } else {
                    $message = t('{description} is no longer available.', ['description' => $item->getDescription()], category: 'commerce');
                    $notice = new OrderNotice([
                        'message' => $message,
                        'type' => 'lineItemRemoved',
                        'attribute' => 'lineItems',
                    ]);
                    $this->addNotice($notice);
                    $this->removeLineItem($item);
                    $recalculateOrder = true;
                }
            }

            // This is run in a validation, but need to run again incase the options
            // data was changed on population of the line item by a plugin.
            if (OrderHelper::mergeDuplicateLineItems($this)) {
                $recalculateOrder = true;
            }

            if ($this->hasEventHandlers(self::EVENT_AFTER_LINE_ITEMS_REFRESHED)) {
                $event = new OrderLineItemsRefreshEvent(
                    lineItems: $this->getLineItems(),
                    recalculate: $recalculateOrder,
                );
                $this->trigger(self::EVENT_AFTER_LINE_ITEMS_REFRESHED, $event);

                $this->setLineItems($event->lineItems);
                $recalculateOrder = $event->recalculate;
            }

            if ($recalculateOrder) {
                $this->recalculate();
                return;
            }
        }

        if ($this->getRecalculationMode() == self::RECALCULATION_MODE_ALL || $this->getRecalculationMode() == self::RECALCULATION_MODE_ADJUSTMENTS_ONLY) {
            //clear adjustments
            $this->setAdjustments([]);

            foreach (app(OrderAdjustments::class)->getAdjusters() as $adjuster) {
                /** @var string|\CraftCms\Commerce\Order\Adjuster\Contracts\AdjusterInterface $adjuster */
                $adjuster = app($adjuster);
                $adjustments = $adjuster->adjust($this);
                $this->setAdjustments(array_merge($this->getAdjustments(), $adjustments));
            }
        }

        if ($this->getRecalculationMode() == self::RECALCULATION_MODE_ALL) {
            // Since shipping adjusters run on the original price, pre discount, let's recalculate
            // if the currently selected shipping method is now not available after adjustments have run.
            $availableMethodOptions = $this->getAvailableShippingMethodOptions();
            if ($this->shippingMethodHandle && !isset($availableMethodOptions[$this->shippingMethodHandle])) {
                $this->shippingMethodHandle = ArrayHelper::firstKey($availableMethodOptions);
                $message = t('The previously-selected shipping method is no longer available.', category: 'commerce');
                $orderNotice = new OrderNotice([
                    'type' => 'shippingMethodChanged',
                    'attribute' => 'shippingMethodHandle',
                    'message' => $message,
                ]);

                $this->addNotice($orderNotice);
                $this->recalculate();
            }
        }
    }

    /**
     * @return ShippingMethodOption[]
     */
    public function getAvailableShippingMethodOptions(): array
    {
        // Matching will contain the core shipping methods and any plugin dynamically returned shipping methods.
        $methods = app(ShippingMethods::class)->getMatchingShippingMethods($this);
        $matchingMethodHandles = ArrayHelper::getColumn($methods, fn(ShippingMethodInterface $sm) => $sm->getHandle());

        // Get all regular methods and add them to the list, for use only when the order is complete.
        if ($this->isCompleted) {
            $allShippingMethods = app(ShippingMethods::class)->getAllShippingMethods()
                ->keyBy(fn(ShippingMethodInterface $sm) => $sm->getHandle())
                ->filter(fn(ShippingMethodInterface $sm) => $sm->getIsEnabled())
                ->all();

            $methods = ArrayHelper::merge($allShippingMethods, $methods);
        }

        $availableShippingMethodOptions = [];

        foreach ($methods as $method) {
            $option = new ShippingMethodOption();

            $storeId = $this->storeId;

            if ($method instanceof ShippingMethod) {
                // @TODO Remove this dateCreated/dateUpdated copy in Commerce 6.0 once ShippingMethodOption no longer exposes those attributes
                foreach (['dateCreated', 'dateUpdated'] as $attribute) {
                    $option->$attribute = $method->$attribute;
                }

                if ($method->storeId !== $storeId) {
                    continue;
                }
            }

            $matchesOrder = ArrayHelper::isIn($method->getHandle(), $matchingMethodHandles);
            $option->setOrder($this);
            $option->enabled = $method->getIsEnabled();
            $option->id = $method->getId();
            $option->name = $method->getName();
            $option->handle = $method->getHandle();
            $option->matchesOrder = $matchesOrder;
            $option->price = $matchesOrder ? $method->getPriceForOrder($this) : 0;
            $option->shippingMethod = $method;
            $option->storeId = $storeId;

            // Add all methods if completed, and only the matching methods when it is not completed.
            if ($this->isCompleted || $option->matchesOrder) {
                $availableShippingMethodOptions[$option->handle] = $option;
            }
        }

        return $availableShippingMethodOptions;
    }

    public function getAvailableGateways(): Collection
    {
        return app(Gateways::class)->getAllCustomerEnabledGatewaysAndAvailableForUseWithOrder($this);
    }

    #[Override]
    public function afterSave(bool $isNew): void
    {
        $lockKey = "order-after-save:$this->number";
        $mutex = Craft::$app->getMutex();
        if (!$mutex->acquire($lockKey, 15)) {
            throw new MutexException($lockKey, 'Could not acquire a lock to save the order.');
        }

        try {
            // Make sure addresses are set before recalculation so that on the next page load
            // the correct adjustments and totals are shown
            if ($this->shippingSameAsBilling) {
                $this->setShippingAddress($this->getBillingAddress());
            }

            if ($this->billingSameAsShipping) {
                $this->setBillingAddress($this->getShippingAddress());
            }

            // @TODO Move recalculate() out of afterSave(); saving should not implicitly recalculate, and the always-recalc-on-save-when-incomplete behavior should be opt-in #COM-40
            $this->recalculate();

            if (!$isNew) {
                $orderRecord = OrderRecord::query()->find($this->id);

                if (!$orderRecord) {
                    throw new Exception('Invalid order ID: ' . $this->id);
                }
            } else {
                $orderRecord = new OrderRecord();
                $orderRecord->id = $this->id;
            }

            $oldStatusId = $orderRecord->orderStatusId;

            $orderRecord->storeId = $this->storeId ?? app(Stores::class)->getCurrentStore()->id;
            $orderRecord->number = $this->number;
            $orderRecord->reference = $this->reference;
            $orderRecord->itemTotal = $this->getItemTotal();
            $orderRecord->itemSubtotal = $this->getItemSubtotal();
            $orderRecord->email = $this->getEmail() ?: '';
            $orderRecord->orderCompletedEmail = $this->orderCompletedEmail;
            $orderRecord->isCompleted = $this->isCompleted;

            $dateOrdered = $this->dateOrdered;
            if (!$dateOrdered && $orderRecord->isCompleted) {
                $dateOrdered = Query::prepareDateForDb(new DateTime());
            }
            $orderRecord->dateOrdered = $dateOrdered;

            $orderRecord->datePaid = $this->datePaid ?: null;
            $orderRecord->dateFirstPaid = $this->dateFirstPaid ?: null;
            $orderRecord->dateAuthorized = $this->dateAuthorized ?: null;
            $orderRecord->shippingMethodHandle = $this->shippingMethodHandle ?? '';
            $orderRecord->shippingMethodName = $this->shippingMethodName ?? '';
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
            $orderRecord->totalQty = $this->getTotalQty();
            $orderRecord->totalWeight = $this->getTotalWeight();
            $orderRecord->currency = $this->currency;
            $orderRecord->lastIp = $this->lastIp;
            $orderRecord->orderLanguage = $this->orderLanguage;
            $orderRecord->orderSiteId = $this->orderSiteId;
            $orderRecord->origin = $this->origin;
            $orderRecord->paymentCurrency = $this->paymentCurrency;
            $orderRecord->customerId = $this->getCustomerId();
            $orderRecord->customerDeleted = $this->getCustomerDeleted();
            $orderRecord->registerUserOnOrderComplete = $this->registerUserOnOrderComplete;
            $orderRecord->saveBillingAddressOnOrderComplete = $this->saveBillingAddressOnOrderComplete;
            $orderRecord->saveShippingAddressOnOrderComplete = $this->saveShippingAddressOnOrderComplete;
            $orderRecord->returnUrl = $this->returnUrl;
            $orderRecord->cancelUrl = $this->cancelUrl;
            $orderRecord->message = $this->message;
            $orderRecord->paidStatus = $this->getPaidStatus();
            $orderRecord->recalculationMode = $this->getRecalculationMode();
            $orderRecord->sourceShippingAddressId = $this->sourceShippingAddressId;
            $orderRecord->sourceBillingAddressId = $this->sourceBillingAddressId;
            $orderRecord->makePrimaryShippingAddress = $this->makePrimaryShippingAddress;
            $orderRecord->makePrimaryBillingAddress = $this->makePrimaryBillingAddress;

            // We want to always have the same date as the element table, based on the logic for updating these in the element service i.e resaving
            $orderRecord->dateUpdated = $this->dateUpdated;
            $orderRecord->dateCreated = $this->dateCreated;

            $currentUser = currentUser();
            $currentUserIsCustomer = ($currentUser && $this->getCustomer() && $currentUser->getCraftUserId() == $this->getCustomer()->id);

            if ($shippingAddress = $this->getShippingAddress()) {
                // If we only set the owner ID an element query will be triggered. If this is a brand-new order we will encounter an error
                // This is because the order record has not been saved.
                // We can avoid this by simply fully setting the owner on the address element. This is also a performance optimisation to avoid an extra query.
                $shippingAddress->setPrimaryOwner($this); // Always ensure the address is owned by the order
                $shippingAddress->title = t('Shipping Address', category: 'commerce'); // Ensure the address is labelled correctly
                Elements::saveElement($shippingAddress, false);
                $orderRecord->shippingAddressId = $shippingAddress->id;
                $this->setShippingAddress($shippingAddress);
                // Set primary shipping if asked
                if ($this->makePrimaryShippingAddress && $currentUserIsCustomer && $this->sourceShippingAddressId) {
                    app(Customers::class)->savePrimaryShippingAddressId($this->getCustomer(), $this->sourceShippingAddressId);
                }
            } else {
                $orderRecord->shippingAddressId = null;
                $this->setShippingAddress(null);
            }

            if ($billingAddress = $this->getBillingAddress()) {
                // If these were set to the same address element, we don't want the same address IDs
                if ($shippingAddress && $billingAddress->id == $shippingAddress->id) {
                    $billingAddress = Elements::duplicateElement($billingAddress,
                        ['owner' => $this, 'title' => t('Billing Address', category: 'commerce')]);
                } else {
                    // If we only set the owner ID an element query will be triggered. If this is a brand-new order we will encounter an error
                    // This is because the order record has not been saved.
                    // We can avoid this by simply fully setting the owner on the address element. This is also a performance optimisation to avoid an extra query.
                    $billingAddress->setOwner($this); // Always ensure the address is owned by the order
                    $billingAddress->title = t('Billing Address', category: 'commerce'); // Ensure the address is labelled correctly
                    Elements::saveElement($billingAddress, false);
                }

                $orderRecord->billingAddressId = $billingAddress->id;
                $this->setBillingAddress($billingAddress);
                // Set primary billing if asked
                if ($this->makePrimaryBillingAddress && $currentUserIsCustomer && $this->sourceBillingAddressId) {
                    app(Customers::class)->savePrimaryBillingAddressId($this->getCustomer(), $this->sourceBillingAddressId);
                }
            } else {
                $orderRecord->billingAddressId = null;
                $this->setBillingAddress(null);
            }

            if ($estimatedShippingAddress = $this->getEstimatedShippingAddress()) {
                // If we only set the owner ID an element query will be triggered. If this is a brand-new order we will encounter an error
                // This is because the order record has not been saved.
                // We can avoid this by simply fully setting the owner on the address element. This is also a performance optimisation to avoid an extra query.
                $estimatedShippingAddress->setPrimaryOwner($this); // Always ensure the address is owned by the order
                Elements::saveElement($estimatedShippingAddress, false);
                $orderRecord->estimatedShippingAddressId = $estimatedShippingAddress->id;
                $this->setEstimatedShippingAddress($estimatedShippingAddress);

                // If estimate billing same as shipping set it here
                if ($this->estimatedBillingSameAsShipping) {
                    $orderRecord->estimatedBillingAddressId = $estimatedShippingAddress->id;
                    $this->setEstimatedBillingAddress($estimatedShippingAddress);
                }
            }

            if (!$this->estimatedBillingSameAsShipping && $estimatedBillingAddress = $this->getEstimatedBillingAddress()) {
                // If we only set the owner ID an element query will be triggered. If this is a brand-new order we will encounter an error
                // This is because the order record has not been saved.
                // We can avoid this by simply fully setting the owner on the address element. This is also a performance optimisation to avoid an extra query.
                $estimatedBillingAddress->setOwner($this); // Always ensure the address is owned by the order
                Elements::saveElement($estimatedBillingAddress, false);
                $orderRecord->estimatedBillingAddressId = $estimatedBillingAddress->id;
                $this->setEstimatedBillingAddress($estimatedBillingAddress);
            }

            $orderRecord->save();

            $this->_saveAdjustments();
            $this->_saveLineItems();
            $this->_saveNotices();
            $this->_deleteOrphanedOrderAddresses();
        } catch (Exception $exception) {
            $mutex->release($lockKey);
            throw $exception;
        }

        $mutex->release($lockKey);

        // We can do this after the lock
        $this->_saveOrderHistory($oldStatusId, $orderRecord->orderStatusId);

        parent::afterSave($isNew);
    }

    public function getShortNumber(): string
    {
        return substr((string) $this->number, 0, 7);
    }

    public function getLink(?string $title = null, array $options = []): ?HtmlString
    {
        if ($title) {
            $options['title'] = $title;
        }

        $title = $title ?: ($this->reference ?: $this->getShortNumber());
        $link = Html::a($title, $this->getCpEditUrl(), $options);

        return new HtmlString($link);
    }

    #[Override]
    public function getCpEditUrl(): ?string
    {
        return Url::cpUrl('commerce/orders/' . $this->id);
    }

    /**
     * Returns the URL to the order's PDF invoice.
     *
     * @param string|null $option The option that should be available to the PDF template (e.g. "receipt")
     * @param string|null $pdfHandle The handle of the PDF to use. If none is passed the default PDF is used.
     * @param bool $inline Whether the PDF should be displayed inline in the browser (default: false)
     * @return string The URL to the order's PDF invoice with a secure token
     */
    public function getPdfUrl(?string $option = null, ?string $pdfHandle = null, bool $inline = false): string
    {
        return app(Pdfs::class)->getPdfUrl($this, $option, $pdfHandle, $inline);
    }

    /**
     * Returns the URL to the cart's load action url with a secure token.
     *
     * @return string|null The URL to the order's load cart URL, or null if the cart is an order
     */
    public function getLoadCartUrl(): ?string
    {
        if ($this->isCompleted) {
            return null;
        }

        return app(Carts::class)->getLoadCartUrl($this);
    }

    public function getCustomerId(): ?int
    {
        return $this->_customerId;
    }

    /**
     * @param int|int[]|null $customerId
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

    public function getCustomerDeleted(): bool
    {
        return $this->_customerDeleted && !$this->getCustomerId();
    }

    public function setCustomerDeleted(bool $customerDeleted): void
    {
        $this->_customerDeleted = $customerDeleted;
    }

    /**
     * Returns the order's customer.
     */
    public function getCustomer(): ?User
    {
        if (!isset($this->_customer)) {
            if (!$this->getCustomerId()) {
                return null;
            }

            if (($this->_customer = Users::getUserById($this->getCustomerId())) === null) {
                $this->_customer = false;
            }
        }

        return $this->_customer ?: null;
    }

    /**
     * Sets the order's customer.
     */
    public function setCustomer(?User $customer = null): void
    {
        $this->_customer = $customer;
        if ($this->_customer) {
            $this->_customerId = $this->_customer->id;
        } else {
            $this->_customerId = null;
        }
    }

    #[\Deprecated(message: 'in 4.0.0. Use [[getCustomer()]] instead.')]
    public function getUser(): ?User
    {
        Deprecator::log('Order::getUser()', 'The `Order::getUser()` is deprecated, use `Order::getCustomer()` instead.');
        return $this->getCustomer();
    }

    /**
     * Sets the orders user based on the email address provided.
     */
    #[\Deprecated(message: 'in 4.3.0. Use [[setCustomer()]] instead.')]
    public function setEmail(?string $email): void
    {
        Deprecator::log(__METHOD__, '`Order::setEmail()` has been deprecated use `Order::setCustomer()` instead.');
        if (!$email) {
            $this->_customer = null;
            $this->_customerId = null;
            return;
        }

        if ($this->_customer && $this->_customer->email === $email) {
            return;
        }

        $user = Users::ensureUserByEmail($email);
        $this->setCustomer($user);
    }

    /**
     * Returns the email for this order. Will always be the customer's email if they exist.
     */
    public function getEmail(): ?string
    {
        return $this->getCustomer()?->email ?? $this->email ?? null;
    }

    /**
     * Returns a masked version of the email for this order.
     */
    public function getMaskedEmail(): string
    {
        if ($email = $this->getEmail()) {
            return $this->_maskEmail($email);
        }

        return '';
    }

    private function _maskEmail($email, $minLength = 3, $maxLength = 10, $mask = "***")
    {
        $atPos = strrpos((string) $email, "@");
        $name = substr((string) $email, 0, $atPos);
        $len = strlen($name);
        $domain = substr((string) $email, $atPos);

        if (($len / 2) < $maxLength) {
            $maxLength = ($len / 2);
        }

        $shortenedEmail = (($len > $minLength) ? substr($name, 0, $maxLength) : "");
        return "{$shortenedEmail}{$mask}{$domain}";
    }

    public function getIsPaid(): bool
    {
        return !$this->hasOutstandingBalance() && $this->isCompleted;
    }

    public function getIsUnpaid(): bool
    {
        return $this->hasOutstandingBalance();
    }

    /**
     * Returns the paymentAmount for this order.
     */
    public function getPaymentAmount(): float
    {
        $paymentAmount = $this->getOutstandingBalance();

        // Only convert if we have differing currencies
        if ($this->currency !== $this->getPaymentCurrency()) {
            $teller = $this->getTeller();
            $tellerTo = app(Currencies::class)->getTeller($this->getPaymentCurrency());
            $outstandingBalanceAmount = $teller->convertToMoney($this->getOutstandingBalance());
            $outstandingBalanceInPaymentCurrency = app(PaymentCurrencies::class)->convertAmount($outstandingBalanceAmount, $this->getPaymentCurrency(), $this->getStore()->id);

            $paymentAmount = (float)$tellerTo->convertToString($outstandingBalanceInPaymentCurrency);
        }

        if (isset($this->_paymentAmount) && $this->_paymentAmount >= 0 && $this->_paymentAmount <= $paymentAmount) {
            return $this->_paymentAmount;
        }

        return $paymentAmount;
    }

    /**
     * Sets the order's payment amount in the order's currency. This amount is not persisted.
     * This will remain null if set to zero or a negative number.
     */
    public function setPaymentAmount(float $amount): void
    {
        $paymentCurrency = app(PaymentCurrencies::class)->getPaymentCurrencyByIso($this->getPaymentCurrency());
        $amount = Currency::round($amount, $paymentCurrency);

        if ($amount > 0) {
            $this->_paymentAmount = $amount;
        }
    }

    /**
     * Returns whether the payment amount currently set is a partial amount of the order's outstanding balance.
     */
    public function isPaymentAmountPartial(): bool
    {
        // NOTE: `PaymentCurrencies::convertCurrency()` was not carried over to the migrated
        // `src/Services/PaymentCurrencies.php` (only `convert()`/`convertAmount()` were), so the
        // legacy `Plugin::getInstance()->getPaymentCurrencies()` facade is used deliberately here —
        // it still implements `convertCurrency()` in terms of the new service's primitives.
        $paymentAmountInPrimaryCurrency = Plugin::getInstance()->getPaymentCurrencies()->convertCurrency($this->getPaymentAmount(), $this->getPaymentCurrency(), $this->currency, true);

        return $paymentAmountInPrimaryCurrency < $this->getOutstandingBalance();
    }

    /**
     * What is the status of the orders payment.
     */
    public function getPaidStatus(): string
    {
        if ($this->getIsPaid() &&
            $this->getTeller()->greaterThan($this->getTotalPrice(), 0) &&
            $this->getTeller()->greaterThan($this->getTotalPaid(), $this->getTotalPrice())
        ) {
            return self::PAID_STATUS_OVERPAID;
        }

        if ($this->getIsPaid()) {
            return self::PAID_STATUS_PAID;
        }

        if ($this->getTeller()->greaterThan($this->getTotalPaid(), 0)) {
            return self::PAID_STATUS_PARTIAL;
        }

        return self::PAID_STATUS_UNPAID;
    }

    /**
     * Customer User link represented as HTML.
     */
    public function getCustomerLinkHtml(): string
    {
        $html = '';
        if ($user = $this->getCustomer()) {
            $email = Html::encode($user->email);
            $html = Html::tag('a', $email, ['href' => $user->getCpEditUrl()]);
        }

        return $html;
    }

    public function getOrderStatusHtml(): string
    {
        if ($status = $this->getOrderStatus()) {
            return $status->getLabelHtml();
        }

        return '';
    }

    /**
     * Paid status represented as HTML.
     */
    public function getPaidStatusHtml(): string
    {
        return match ($this->getPaidStatus()) {
            self::PAID_STATUS_OVERPAID => app(StatusHtml::class)->statusLabelHtml(['color' => 'blue', 'label' => t('Overpaid', category: 'commerce')]),
            self::PAID_STATUS_PAID => app(StatusHtml::class)->statusLabelHtml(['color' => 'green', 'label' => t('Paid', category: 'commerce')]),
            self::PAID_STATUS_PARTIAL => app(StatusHtml::class)->statusLabelHtml(['color' => 'orange', 'label' => t('Partial', category: 'commerce')]),
            self::PAID_STATUS_UNPAID => app(StatusHtml::class)->statusLabelHtml(['color' => 'red', 'label' => t('Unpaid', category: 'commerce')]),
            default => '',
        };
    }

    /**
     * Returns the raw total of the order, which is the total of all line items and adjustments. This
     * number can be negative, so it is not the price of the order.
     *
     * @see Order::getTotalPrice() The actual total price of the order.
     */
    public function getTotal(): float
    {
        $itemSubtotal = $this->getItemSubtotal();
        $adjustmentsTotal = $this->getAdjustmentsTotal();
        return (float)$this->getTeller()->add($itemSubtotal, $adjustmentsTotal);
    }

    /**
     * Get the total price of the order, whose minimum value is enforced by the configured
     * {@see Store::getMinimumTotalPriceStrategy() strategy set for minimum total price}.
     */
    public function getTotalPrice(): float
    {
        $total = (float)$this->getTeller()->add($this->getItemSubtotal(), $this->getAdjustmentsTotal());
        // Don't get the pre-rounded total.
        $strategy = $this->getStore()->getMinimumTotalPriceStrategy();

        if ($strategy === Store::MINIMUM_TOTAL_PRICE_STRATEGY_ZERO) {
            return (float)$this->getTeller()->max(0, $total);
        }

        if ($strategy === Store::MINIMUM_TOTAL_PRICE_STRATEGY_SHIPPING) {
            return (float)$this->getTeller()->max($this->getTotalShippingCost(), $total);
        }

        return $total;
    }

    public function getItemTotal(): float
    {
        $total = 0;
        $teller = $this->getTeller();
        foreach ($this->getLineItems() as $lineItem) {
            $total = (float)$teller->add($total, $lineItem->getTotal());
        }

        return $total;
    }

    public function hasShippableItems(): bool
    {
        return array_any($this->getLineItems(), fn($item) => $item->getIsShippable());
    }

    /**
     * Returns the difference between the order amount and amount paid.
     */
    public function getOutstandingBalance(): float
    {
        return (float)$this->getTeller()->subtract($this->getTotalPrice(), $this->getTotalPaid());
    }

    public function hasOutstandingBalance(): bool
    {
        return $this->getTeller()->greaterThan($this->getOutstandingBalance(), 0);
    }

    /**
     * Returns the total `purchase` and `captured` transactions belonging to this order.
     */
    public function getTotalPaid(): float
    {
        if ($this->id === null) {
            return 0;
        }

        if ($this->_transactions === null) {
            $this->_transactions = app(Transactions::class)->getAllTransactionsByOrderId($this->id);
        }

        $transactions = collect($this->_transactions);

        $paid = $transactions->filter(fn($transaction) => $transaction->status == TransactionRecord::STATUS_SUCCESS
            && in_array($transaction->type, [TransactionRecord::TYPE_PURCHASE, TransactionRecord::TYPE_CAPTURE]))->sum('amount');

        $refunded = $transactions->filter(fn($transaction) => $transaction->status == TransactionRecord::STATUS_SUCCESS
            && $transaction->type == TransactionRecord::TYPE_REFUND)->sum('amount');

        return (float)$this->getTeller()->subtract($paid, $refunded);
    }

    public function getTotalAuthorized(): float
    {
        if (!$this->id) {
            return 0;
        }

        $authorized = 0;
        $captured = 0;

        if ($this->_transactions === null) {
            $this->_transactions = app(Transactions::class)->getAllTransactionsByOrderId($this->id);
        }

        foreach ($this->_transactions as $transaction) {
            $isSuccess = ($transaction->status == TransactionRecord::STATUS_SUCCESS);
            $isAuth = ($transaction->type == TransactionRecord::TYPE_AUTHORIZE);
            $isCapture = ($transaction->type == TransactionRecord::TYPE_CAPTURE);

            if (!$isSuccess) {
                continue;
            }

            if ($isAuth) {
                $authorized += $transaction->amount;
                continue;
            }

            if ($isCapture) {
                $captured += $transaction->amount;
            }
        }

        return (float)$this->getTeller()->subtract($authorized, $captured);
    }

    /**
     * Returns whether this order is the user's current active cart.
     */
    public function getIsActiveCart(): bool
    {
        $cart = app(Carts::class)->getCart();

        return $cart->id == $this->id;
    }

    /**
     * Returns whether the order has any items in it.
     */
    public function getIsEmpty(): bool
    {
        return $this->getTotalQty() == 0;
    }

    public function hasLineItems(): bool
    {
        return (bool)$this->getLineItems();
    }

    /**
     * Returns whether the order contains the given purchasable IDs.
     *
     * @param mixed $purchasableIds One or more purchasable IDs or purchasable models to check for.
     * @param ContainsPurchasablesMatch $match The match mode.
     */
    public function hasPurchasables(mixed $purchasableIds, ContainsPurchasablesMatch $match = ContainsPurchasablesMatch::Any): bool
    {
        if (!is_array($purchasableIds)) {
            $purchasableIds = [$purchasableIds];
        }

        $orderPurchasableIds = collect($this->getLineItems())
            ->pluck('purchasableId')
            ->filter(fn($id) => $id !== null);

        $requestedIds = collect($purchasableIds)
            ->map(fn($id) => $id instanceof PurchasableInterface ? $id->getId() : $id)
            ->filter(fn($id) => $id !== null);

        if ($match === ContainsPurchasablesMatch::Any) {
            return $orderPurchasableIds->intersect($requestedIds)->isNotEmpty();
        }

        if ($match === ContainsPurchasablesMatch::Only) {
            // If there are custom line items (null purchasableId), the order
            // has purchasables beyond what was specified, so it can't be only.
            $hasCustomLineItems = collect($this->getLineItems())
                ->pluck('purchasableId')
                ->contains(null);

            if ($hasCustomLineItems) {
                return false;
            }

            return $orderPurchasableIds->diff($requestedIds)->isEmpty()
                && $requestedIds->diff($orderPurchasableIds)->isEmpty();
        }

        // ContainsPurchasablesMatch::All — every requested purchasable must exist in the order
        return $requestedIds->every(fn($id) => $orderPurchasableIds->contains($id));
    }

    public function getTotalCommittedStock(): int
    {
        return app(Inventory::class)->getInventoryFulfillmentLevels($this)->sum('committedQuantity') ?? 0;
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
            $lineItems = $this->id ? app(LineItems::class)->getAllLineItemsByOrderId($this->id) : [];
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

        $this->_lineItems = $lineItems;
    }

    public function _getAdjustmentsTotalByType(array|string $types, bool $included = false): float|int
    {
        $amount = 0;
        $teller = $this->getTeller();

        if (is_string($types)) {
            $types = StringHelper::split($types);
        }

        foreach ($this->getAdjustments() as $adjustment) {
            if ($adjustment->included == $included && in_array($adjustment->type, $types, false)) {
                $amount = (float)$teller->add($amount, $adjustment->amount);
            }
        }

        return $amount;
    }

    /**
     * The total amount of tax adjustments that are additive taxes that affect total price.
     */
    public function getTotalTax(): float
    {
        return $this->_getAdjustmentsTotalByType('tax');
    }

    /**
     * The total amount of tax adjustments on the order that are included in the price, and do not affect total price.
     */
    public function getTotalTaxIncluded(): float
    {
        return $this->_getAdjustmentsTotalByType('tax', true);
    }

    /**
     * The total amount of discount adjustments.
     */
    public function getTotalDiscount(): float
    {
        return $this->_getAdjustmentsTotalByType('discount');
    }

    /**
     * The total amount of shipping adjustments.
     */
    public function getTotalShippingCost(): float
    {
        return $this->_getAdjustmentsTotalByType('shipping');
    }

    public function getTotalWeight(): float
    {
        $weight = 0;
        foreach ($this->getLineItems() as $item) {
            $weight += ($item->qty * $item->weight);
        }

        return $weight;
    }

    /**
     * Returns the total promotional amount.
     */
    public function getTotalPromotionalAmount(): float
    {
        $value = 0;
        $teller = $this->getTeller();
        foreach ($this->getLineItems() as $item) {
            $value = (float)$teller->add(
                $value,
                $teller->multiply($item->qty, $item->getPromotionalAmount()),
            );
        }

        return $value;
    }

    /**
     * Returns the total sale amount.
     *
     * @deprecated in 5.0.0. Use {@see Order::getTotalPromotionalAmount()} instead.
     */
    #[\Deprecated(message: 'in 5.0.0. Use [[getTotalPromotionalAmount()]] instead.')]
    public function getTotalSaleAmount(): float
    {
        Deprecator::log(__METHOD__, '`getTotalSaleAmount()` method has been deprecated. Use `getTotalPromotionalAmount()` instead.');
        return $this->getTotalPromotionalAmount();
    }

    /**
     * Returns the total of all line item's subtotals.
     */
    public function getItemSubtotal(): float
    {
        $value = 0;
        $teller = $this->getTeller();
        foreach ($this->getLineItems() as $item) {
            $value = (float)$teller->add($value, $item->getSubtotal());
        }

        return $value;
    }

    /**
     * Returns the total of adjustments made to order.
     */
    public function getAdjustmentSubtotal(): float
    {
        $value = 0;
        $teller = $this->getTeller();
        foreach ($this->getAdjustments() as $adjustment) {
            if (!$adjustment->included) {
                $value = (float)$teller->add($value, $adjustment->amount);
            }
        }

        return (float)$value;
    }

    /**
     * @return OrderAdjustment[]|null
     */
    public function getAdjustments(): ?array
    {
        if (isset($this->_orderAdjustments)) {
            return $this->_orderAdjustments;
        }

        if ($this->id) {
            $this->setAdjustments(app(OrderAdjustments::class)->getAllOrderAdjustmentsByOrderId($this->id));
        }

        return $this->_orderAdjustments ?? [];
    }

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
        $this->_orderAdjustments = [];

        foreach ($adjustments as $adjustment) {
            $adjustment->setOrder($this);
        }

        $this->_orderAdjustments = $adjustments;
    }

    public function getAdjustmentsTotal(): float
    {
        $amount = 0;
        $teller = $this->getTeller();
        foreach ($this->getAdjustments() as $adjustment) {
            if (!$adjustment->included) {
                $amount = (float)$teller->add($amount, $adjustment->amount);
            }
        }

        return $amount;
    }

    /**
     * Get the shipping address on the order.
     */
    public function getShippingAddress(): ?AddressElement
    {
        if (!isset($this->_shippingAddress) && $this->shippingAddressId) {
            /** @var AddressElement|null $address */
            $address = AddressElement::find()
                ->owner($this)
                ->id($this->shippingAddressId)
                ->one();

            $this->_shippingAddress = $address;
        }

        return $this->_shippingAddress;
    }

    /**
     * Set the shipping address on the order.
     */
    public function setShippingAddress(AddressElement|array|null $address): void
    {
        if ($address === null) {
            $this->shippingAddressId = null;
            $this->_shippingAddress = null;
            return;
        }

        if (is_array($address)) {
            unset($address['id']);
            $addressElement = $this->_shippingAddress ?: new AddressElement();
            $addressElement->setAttributes($address);
            $this->_populateAddressNameAttributes($addressElement, $address);
            $addressElement->setPrimaryOwner($this);
            $address = $addressElement;
        }

        // Ensure that address can only belong to this order
        if ($address->getPrimaryOwnerId() != $this->id) {
            throw new InvalidArgumentException('Can not set a shipping address on the order that is not owned by the order.');
        }

        $this->shippingAddressId = $address->id;
        $address->title = t('Shipping Address', category: 'commerce');
        $this->_shippingAddress = $address;
    }

    public function removeShippingAddress(): void
    {
        $this->shippingAddressId = null;
        $this->_shippingAddress = null;
    }

    public function getEstimatedShippingAddress(): ?AddressElement
    {
        if (!isset($this->_estimatedShippingAddress) && $this->estimatedShippingAddressId) {
            /** @var AddressElement|null $address */
            $address = AddressElement::find()->owner($this)->id($this->estimatedShippingAddressId)->one();
            $this->_estimatedShippingAddress = $address;
        }

        return $this->_estimatedShippingAddress;
    }

    public function setEstimatedShippingAddress(AddressElement|array|null $address): void
    {
        if ($address === null) {
            $this->estimatedShippingAddressId = null;
            $this->_estimatedShippingAddress = null;
            return;
        }

        if (!$address instanceof AddressElement) {
            $addressElement = new AddressElement();
            $addressElement->setAttributes($address);
            $address = $addressElement;
        }

        $this->estimatedShippingAddressId = $address->id;
        $this->_estimatedShippingAddress = $address;
    }

    /**
     * Get the billing address on the order.
     */
    public function getBillingAddress(): ?AddressElement
    {
        if (!isset($this->_billingAddress) && $this->billingAddressId) {
            /** @var AddressElement|null $address */
            $address = AddressElement::find()
                ->owner($this)
                ->id($this->billingAddressId)
                ->one();

            $this->_billingAddress = $address;
        }

        return $this->_billingAddress;
    }

    /**
     * Set the billing address on the order.
     */
    public function setBillingAddress(AddressElement|array|null $address): void
    {
        if ($address === null) {
            $this->billingAddressId = null;
            $this->_billingAddress = null;
            return;
        }

        if (is_array($address)) {
            unset($address['id']); // only ever allow setting of the address data
            $addressElement = $this->_billingAddress ?: new AddressElement();
            $addressElement->setAttributes($address);
            $this->_populateAddressNameAttributes($addressElement, $address);
            $addressElement->setPrimaryOwner($this);
            $address = $addressElement;
        }

        // Ensure that address can only belong to this order
        if ($address->getPrimaryOwnerId() !== $this->id) {
            throw new InvalidArgumentException('Can not set a billing address on the order that is not owned by the order.');
        }

        $address->ownerId = $this->id;
        $this->billingAddressId = $address->id;
        $address->title = t('Billing Address', category: 'commerce');
        $this->_billingAddress = $address;
    }

    public function removeBillingAddress(): void
    {
        $this->billingAddressId = null;
        $this->_billingAddress = null;
    }

    /**
     * Returns whether the billing and shipping addresses' data matches.
     *
     * @param string[]|null $attributes array of attributes names on which to match the addresses
     */
    public function hasMatchingAddresses(?array $attributes = null): bool
    {
        $addressAttributes = new ReflectionClass(AddressInterface::class)->getMethods();
        $addressAttributes = array_map(static fn(ReflectionMethod $method) => // Remove `get` and lower case first character
        lcfirst(substr($method->name, 3)), $addressAttributes);

        $relationCustomFieldHandles = [];
        $customFieldHandles = array_map(static function(FieldInterface $field) use (&$relationCustomFieldHandles) {
            if ($field instanceof BaseRelationField) {
                $relationCustomFieldHandles[] = $field->handle;
            }

            return $field->handle;
        }, new AddressElement()->getFieldLayout()->getCustomFields());

        $nameTraitProperties = array_map(static fn(ReflectionProperty $property) => $property->name, new ReflectionClass(NameTrait::class)->getProperties());

        $toArrayHandles = [...$nameTraitProperties, ...$addressAttributes, ...$customFieldHandles];

        if (!empty($attributes)) {
            $toArrayHandles = array_intersect($toArrayHandles, $attributes);
        }

        // Figure out if we need to do any extra work for custom fields
        $toArrayRelationFields = !empty($relationCustomFieldHandles) ? array_intersect($toArrayHandles, $relationCustomFieldHandles) : [];

        $matchingShippingAddress = [];
        if ($this->getShippingAddress() instanceof AddressElement) {
            $matchingShippingAddress = $this->getShippingAddress()->toArray(array_diff($toArrayHandles, $toArrayRelationFields));
        }

        $matchingBillingAddress = [];
        if ($this->getBillingAddress() instanceof AddressElement) {
            $matchingBillingAddress = $this->getBillingAddress()->toArray(array_diff($toArrayHandles, $toArrayRelationFields));
        }

        // Add any relational custom fields to the matching arrays
        if (!empty($toArrayRelationFields)) {
            foreach ($toArrayRelationFields as $handle) {
                if ($this->getShippingAddress() instanceof AddressElement) {
                    $matchingShippingAddress[$handle] = $this->getShippingAddress()->getFieldValue($handle)?->ids();
                }

                if ($this->getBillingAddress() instanceof AddressElement) {
                    $matchingBillingAddress[$handle] = $this->getBillingAddress()->getFieldValue($handle)?->ids();
                }
            }
        }

        return $matchingBillingAddress == $matchingShippingAddress;
    }

    public function getEstimatedBillingAddress(): ?AddressElement
    {
        if (!isset($this->_estimatedBillingAddress) && $this->estimatedBillingAddressId) {
            /** @var AddressElement|null $address */
            $address = AddressElement::find()->owner($this)->id($this->estimatedBillingAddressId)->one();
            $this->_estimatedBillingAddress = $address;
        }

        return $this->_estimatedBillingAddress;
    }

    public function setEstimatedBillingAddress(AddressElement|array|null $address): void
    {
        if ($address === null) {
            $this->estimatedBillingAddressId = null;
            $this->_estimatedBillingAddress = null;
            return;
        }

        if (!$address instanceof AddressElement) {
            $addressElement = new AddressElement();
            $addressElement->setAttributes($address);
            $address = $addressElement;
        }

        $this->estimatedBillingAddressId = $address->id;
        $this->_estimatedBillingAddress = $address;
    }

    /**
     * @deprecated in 3.4.18. Use `$shippingMethodHandle` or `$shippingMethodName` instead.
     */
    #[\Deprecated(message: 'in 3.4.18. Use `$shippingMethodHandle` or `$shippingMethodName` instead.')]
    public function getShippingMethod(): ?ShippingMethod
    {
        return app(ShippingMethods::class)->getShippingMethodByHandle((string)$this->shippingMethodHandle);
    }

    public function getGateway(): ?GatewayInterface
    {
        if ($this->gatewayId === null && $this->paymentSourceId === null) {
            return null;
        }

        $gateway = null;

        // sources before gateways
        if ($this->paymentSourceId) {
            if ($paymentSource = app(PaymentSources::class)->getPaymentSourceById($this->paymentSourceId)) {
                $gateway = app(Gateways::class)->getGatewayById($paymentSource->gatewayId);
            }
        } else {
            if ($this->gatewayId) {
                $gateway = app(Gateways::class)->getGatewayById((int)$this->gatewayId);
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
            $this->_paymentCurrency = $this->getStore()->getCurrency()?->getCode();
        }

        return $this->_paymentCurrency;
    }

    public function setPaymentCurrency(string $value): void
    {
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

        if (($paymentSource = app(PaymentSources::class)->getPaymentSourceByIdAndUserId($this->paymentSourceId, $user->id)) === null) {
            throw new InvalidArgumentException("Invalid payment source ID: $this->paymentSourceId");
        }

        return $paymentSource;
    }

    public function setPaymentSource(?PaymentSource $paymentSource): void
    {
        // Setting the payment source to null clears it
        if ($paymentSource === null) {
            $this->paymentSourceId = null;
            return;
        }

        // We are now dealing with a PaymentSource
        $customer = $this->getCustomer();
        if ($customer?->id && $paymentSource->getCustomer()?->id !== $customer->id) {
            throw new InvalidArgumentException('PaymentSource is not owned by the user of the order.');
        }

        $this->paymentSourceId = $paymentSource->id;
        $this->gatewayId = null;
    }

    public function setGatewayId(int $gatewayId): void
    {
        $this->gatewayId = $gatewayId;
        $this->paymentSourceId = null;
    }

    /**
     * @return OrderHistory[]
     */
    public function getHistories(): array
    {
        if ($this->id === null) {
            return [];
        }

        $histories = app(OrderHistories::class)->getAllOrderHistoriesByOrderId($this->id);

        foreach ($histories as $history) {
            $history->setOrder($this);
        }

        return $histories;
    }

    /**
     * Set transactions on the order. Set to null to clear cache and force next getTransactions() call to get the latest transactions.
     *
     * @param Transaction[]|null $transactions
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
        if ($this->id === null) {
            $this->_transactions = [];
        }

        if ($this->_transactions === null) {
            $transactions = app(Transactions::class)->getAllTransactionsByOrderId($this->id);

            foreach ($transactions as $transaction) {
                $transaction->setOrder($this);
            }

            $this->_transactions = $transactions;
        }

        return $this->_transactions;
    }

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

    public function getOrderStatus(): ?OrderStatus
    {
        return $this->orderStatusId !== null ? app(OrderStatuses::class)->getOrderStatusById($this->orderStatusId, $this->storeId) : null;
    }

    /**
     * Get the site for the order.
     */
    public function getOrderSite(): ?Site
    {
        if (!$this->orderSiteId) {
            return null;
        }

        return Sites::getSiteById($this->orderSiteId);
    }

    #[Override]
    public function getMetadata(): array
    {
        $metadata = [];

        if ($this->isCompleted) {
            $metadata[t('Reference', category: 'commerce')] = Html::encode($this->reference);
            $metadata[t('Date Ordered', category: 'commerce')] = I18N::getFormatter()->asDatetime($this->dateOrdered, 'short');
        }

        $metadata[t('Coupon Code', category: 'commerce')] = Html::encode($this->couponCode);

        $orderSite = $this->getOrderSite();
        $metadata[t('Order Site', category: 'commerce')] = Html::encode($orderSite?->getName() ?? '');

        $metadata[t('Shipping Method', category: 'commerce')] = Html::encode($this->shippingMethodName ?? '');

        $metadata[t('ID')] = $this->id;
        $metadata[t('Short Number', category: 'commerce')] = $this->getShortNumber();
        $metadata[t('Paid Status', category: 'commerce')] = $this->getPaidStatusHtml();
        $metadata[t('Total Price', category: 'commerce')] = $this->totalPriceAsCurrency;
        $metadata[t('Paid Amount', category: 'commerce')] = $this->totalPaidAsCurrency;
        $metadata[t('Origin', category: 'commerce')] = Html::encode($this->origin);

        return array_merge($metadata, parent::getMetadata());
    }

    #[Override]
    public function beforeDelete(): bool
    {
        if (!parent::beforeDelete()) {
            return false;
        }

        // Capture line items before the cascade delete fires so afterDelete() can refresh stock caches
        if ($this->isCompleted) {
            $this->_deletingLineItems = $this->getLineItems();
        }

        return true;
    }

    #[Override]
    public function afterDelete(): void
    {
        parent::afterDelete();

        if ($this->isCompleted) {
            foreach ($this->_deletingLineItems as $lineItem) {
                $purchasable = $lineItem->getPurchasable();
                if (($purchasable instanceof Purchasable || $purchasable instanceof NewPurchasable) && $purchasable::hasInventory() && $purchasable->inventoryTracked) {
                    app(Purchasables::class)->updateStoreStockCache($purchasable, true);
                }
            }
        }
    }

    /**
     * Returns non-admin notices. Admin notices are excluded by default.
     *
     * @param string|null $type type name. Use null to retrieve notices for all types.
     * @param string|null $attribute attribute name. Use null to retrieve notices for all attributes.
     * @return OrderNotice[] notices for all types or the specified type / attribute. Empty array is returned if no notice.
     */
    public function getNotices(?string $type = null, ?string $attribute = null): array
    {
        $notices = array_values(array_filter($this->_notices, fn(OrderNotice $n) => $n->noticeType === OrderNoticeType::Customer));
        return $this->_filterNotices($notices, $type, $attribute);
    }

    /**
     * Returns admin-only notices, optionally filtered by type and/or attribute.
     *
     * @return OrderNotice[]
     */
    public function getAdminNotices(?string $type = null, ?string $attribute = null): array
    {
        $notices = array_values(array_filter($this->_notices, fn(OrderNotice $n) => $n->noticeType === OrderNoticeType::Admin));
        return $this->_filterNotices($notices, $type, $attribute);
    }

    /**
     * Adds a new notice.
     */
    public function addNotice(OrderNotice $notice): void
    {
        $notice->setOrder($this);
        $this->_notices[] = $notice;
    }

    /**
     * Returns the first non-admin notice matching the specified type or attribute.
     */
    public function getFirstNotice($type = null, $attribute = null): ?OrderNotice
    {
        return ArrayHelper::firstValue($this->getNotices($type, $attribute));
    }

    /**
     * Adds a list of notices.
     *
     * @param OrderNotice[] $notices an array of notices.
     */
    public function addNotices(array $notices): void
    {
        foreach ($notices as $notice) {
            $this->addNotice($notice);
        }
    }

    /**
     * Removes notices matching the given criteria, scoped to the specified notice types.
     *
     * By default only customer notices are cleared, preserving admin notices for backwards compatibility.
     * Pass one or more {@see OrderNoticeType} values to control which notice types are affected.
     *
     * @param string|null $type type name. Use null to remove notices for all types.
     * @param string|null $attribute attribute name. Use null to remove notices for all attributes.
     * @param OrderNoticeType|OrderNoticeType[]|null $noticeTypes Notice type(s) to clear. Defaults to customer notices only.
     */
    public function clearNotices(?string $type = null, ?string $attribute = null, array|OrderNoticeType|null $noticeTypes = null): void
    {
        if ($noticeTypes === null) {
            $noticeTypes = [OrderNoticeType::Customer];
        } elseif ($noticeTypes instanceof OrderNoticeType) {
            $noticeTypes = [$noticeTypes];
        }

        $targetNotices = array_values(array_filter($this->_notices, fn(OrderNotice $n) => in_array($n->noticeType, $noticeTypes)));
        $preservedNotices = array_values(array_filter($this->_notices, fn(OrderNotice $n) => !in_array($n->noticeType, $noticeTypes)));

        if ($type === null && $attribute === null) {
            $remaining = [];
        } elseif ($type !== null && $attribute === null) {
            $remaining = array_values(array_filter($targetNotices, fn(OrderNotice $n) => $n->type !== $type));
        } elseif ($type === null && $attribute !== null) {
            $remaining = array_values(array_filter($targetNotices, fn(OrderNotice $n) => $n->attribute !== $attribute));
        } else {
            $remaining = array_values(array_filter($targetNotices, fn(OrderNotice $n) => !($n->type === $type && $n->attribute === $attribute)));
        }

        $this->_notices = array_merge($preservedNotices, $remaining);
    }

    /**
     * Returns a value indicating whether there are any non-admin notices.
     */
    public function hasNotices(?string $type = null, ?string $attribute = null): bool
    {
        return !empty($this->getNotices($type, $attribute));
    }

    /**
     * Returns whether there are any admin notices.
     */
    public function hasAdminNotices(): bool
    {
        return !empty($this->getAdminNotices());
    }

    /**
     * Filters an array of notices by type and/or attribute.
     *
     * @param OrderNotice[] $notices
     * @return OrderNotice[]
     */
    private function _filterNotices(array $notices, ?string $type, ?string $attribute): array
    {
        if ($type === null && $attribute === null) {
            return $notices;
        }

        if ($type !== null && $attribute === null) {
            return ArrayHelper::where($notices, 'type', $type);
        }

        if ($type === null && $attribute !== null) {
            return ArrayHelper::where($notices, 'attribute', $attribute);
        }

        return ArrayHelper::where($notices, fn(OrderNotice $n) => $n->attribute === $attribute && $n->type === $type, true, true, true);
    }

    public function validateGatewayId(string $attribute): void
    {
        if ($this->gatewayId && !$this->getGateway()) {
            $this->errors()->add($attribute, t('Invalid gateway: {value}', category: 'commerce'));
        }
    }

    public function validatePaymentSourceId(string $attribute): void
    {
        try {
            // this will confirm the payment source is valid and belongs to the orders customer
            $this->getPaymentSource();
        } catch (InvalidConfigException $e) {
            Log::error($e);
            $this->errors()->add($attribute, t('Invalid payment source ID: {value}', category: 'commerce'));
        }
    }

    public function validatePaymentCurrency(string $attribute): void
    {
        try {
            // this will confirm the payment source is valid and belongs to the orders customer
            $this->getPaymentCurrency();
        } catch (InvalidConfigException) {
            $this->errors()->add($attribute, t('Invalid payment source ID: {value}', category: 'commerce'));
        }
    }

    /**
     * Validates addresses, and also adds prefixed validation errors to order.
     *
     * @param string $attribute the attribute being validated
     */
    public function validateAddress(string $attribute): void
    {
        /** @var AddressElement|null $address */
        $address = $this->$attribute;

        // Set live scenario for addresses to match CP
        $address?->ruleset->useScenario(ElementRules::SCENARIO_LIVE);

        if ($address && !$address->validate()) {
            $this->addModelErrors($address, $attribute);
        }

        $marketLocationCondition = $this->getStore()->getSettings()->getMarketAddressCondition();
        if ($address && count($marketLocationCondition->getConditionRules()) > 0 && !$marketLocationCondition->matchElement($address)) {
            $this->errors()->add($attribute, t('The address provided is outside the store\'s market.', category: 'commerce'));
        }
    }

    /**
     * Validates that address country is in the allowed list.
     *
     * @param string $attribute the attribute being validated
     */
    public function validateAddressCountry(string $attribute): void
    {
        $address = $this->$attribute;
        if ($address && $address->countryCode) {
            $countriesList = array_keys($this->getStore()->getSettings()->getCountriesList());
            if (count($countriesList) && !in_array($address->countryCode, $countriesList, false)) {
                $this->errors()->add($attribute, t('Country not allowed.', category: 'commerce'));
            }
        }
    }

    /**
     * Validates that shipping address isn't being set to be the same as billing address, when
     * billing address is set to be shipping address.
     *
     * @param string $attribute the attribute being validated
     */
    public function validateAddressReuse(string $attribute): void
    {
        if ($this->shippingSameAsBilling && $this->billingSameAsShipping) {
            $this->errors()->add($attribute, t('shippingSameAsBilling and billingSameAsShipping can\'t both be set.', category: 'commerce'));
        }
    }

    /**
     * Validates line items, and also adds prefixed validation errors to order.
     */
    public function validateLineItems(): void
    {
        OrderHelper::normalizeLineItemPurchasableAvailability($this);
        OrderHelper::mergeDuplicateLineItems($this);

        foreach ($this->getLineItems() as $key => $lineItem) {
            if (!$lineItem->validate()) {
                $this->addModelErrors($lineItem, "lineItems.$key");
            }
        }
    }

    public function validateCouponCode($attribute): void
    {
        $recalculateAll = $this->getRecalculationMode() == self::RECALCULATION_MODE_ALL;
        $recalculateAll = $recalculateAll || $this->getRecalculationMode() == self::RECALCULATION_MODE_ADJUSTMENTS_ONLY;
        if ($recalculateAll && $this->$attribute && !app(Discounts::class)->orderCouponAvailable($this, $explanation)) {
            $notice = new OrderNotice([
                'type' => 'invalidCouponRemoved',
                'attribute' => $attribute,
                'message' => t('Coupon removed: {explanation}', [
                    'explanation' => $explanation,
                ], category: 'commerce'),
            ]);
            $this->addNotice($notice);
            $this->$attribute = null;
        }
    }

    public function validateOrganizationTaxIdAsVatId($attribute): void
    {
        /** @var AddressElement $address */
        $address = $this->$attribute;

        // Skip on empty
        if (!$address->organizationTaxId) {
            return;
        }

        if (app(Vat::class)->isValidVatId($address->organizationTaxId)) {
            return;
        }

        $address->errors()->add('organizationTaxId', t('Invalid VAT ID.', category: 'commerce'));
        $this->addModelErrors($address, $attribute);
    }

    /**
     * @return OrderQuery The newly created OrderQuery instance.
     */
    #[Override]
    public static function find(): OrderQuery
    {
        return new OrderQuery();
    }

    /**
     * Order has a single, shared field layout keyed by element type (not a per-instance
     * `fieldLayoutId`), so this overrides the default `HasCustomFields::getFieldLayout()` rather
     * than using the `HasFieldLayout` concern — that concern is for classes that own/configure a
     * field layout for other elements (e.g. a section or product type), not for elements that
     * simply consume one shared-by-type layout.
     */
    #[Override]
    public function getFieldLayout(): FieldLayout
    {
        return Fields::getLayoutByType(static::class);
    }

    #[Override]
    protected function htmlAttributes(string $context): array
    {
        $attributes = parent::htmlAttributes($context);
        $attributes['data'] = ['number' => $this->number];
        return $attributes;
    }

    #[Override]
    protected function attributeHtml(string $attribute): string
    {
        switch ($attribute) {
            case 'orderStatus':
            {
                return $this->getOrderStatus() ? $this->getOrderStatus()->getLabelHtml() : '';
            }
            case 'customer':
            {
                return $this->getCustomerLinkHtml();
            }
            case 'shippingFullName':
            {
                return $this->getShippingAddress() ? Html::encode($this->getShippingAddress()->fullName ?? '') : '';
            }
            case 'shippingFirstName':
            {
                return $this->getShippingAddress() ? Html::encode($this->getShippingAddress()->firstName ?? '') : '';
            }
            case 'shippingLastName':
            {
                return $this->getShippingAddress() ? Html::encode($this->getShippingAddress()->lastName ?? '') : '';
            }
            case 'billingFullName':
            {
                return $this->getBillingAddress() ? Html::encode($this->getBillingAddress()->fullName ?? '') : '';
            }
            case 'billingFirstName':
            {
                return $this->getBillingAddress() ? Html::encode($this->getBillingAddress()->firstName ?? '') : '';
            }
            case 'billingLastName':
            {
                return $this->getBillingAddress() ? Html::encode($this->getBillingAddress()->lastName ?? '') : '';
            }
            case 'shippingOrganizationName':
            {
                return $this->getShippingAddress() ? Html::encode($this->getShippingAddress()->organization ?? '') : '';
            }
            case 'billingOrganizationName':
            {
                return $this->getBillingAddress() ? Html::encode($this->getBillingAddress()->organization ?? '') : '';
            }
            case 'shippingMethodName':
            {
                return Html::encode($this->shippingMethodName ?? '');
            }
            case 'gatewayName':
            {
                return Html::encode($this->getGateway()->name ?? '');
            }
            case 'paidStatus':
            {
                return $this->getPaidStatusHtml();
            }
            case 'totalPaid':
            {
                return $this->storedTotalPaidAsCurrency;
            }
            case 'itemTotal':
            {
                return $this->storedItemTotalAsCurrency;
            }
            case 'itemSubtotal':
            {
                return $this->storedItemSubtotalAsCurrency;
            }
            case 'totalQty':
            {
                return (string)$this->storedTotalQty;
            }
            case 'total':
            {
                return $this->totalAsCurrency;
            }
            case 'totalPrice':
            {
                return $this->storedTotalPriceAsCurrency;
            }
            case 'totalShippingCost':
            {
                return $this->storedTotalShippingCostAsCurrency;
            }
            case 'totalDiscount':
            {
                return $this->storedTotalDiscountAsCurrency;
            }
            case 'totalTax':
            {
                return $this->storedTotalTaxAsCurrency;
            }
            case 'totalIncludedTax':
            {
                return $this->storedTotalTaxIncludedAsCurrency;
            }
            case 'totals':
            {
                $miniTable = [];

                $miniTable[] = [
                    'label' => t('Qty', category: 'commerce'),
                    'value' => $this->storedTotalQty,
                ];

                if ($this->itemSubtotal > 0) {
                    $miniTable[] = [
                        'label' => t('Items', category: 'commerce'),
                        'value' => $this->itemSubtotalAsCurrency,
                    ];
                }

                if ($this->storedTotalDiscount < 0) {
                    $miniTable[] = [
                        'label' => t('Discounts', category: 'commerce'),
                        'value' => $this->storedTotalDiscountAsCurrency,
                    ];
                }

                if ($this->storedTotalShippingCost > 0) {
                    $miniTable[] = [
                        'label' => t('Shipping', category: 'commerce'),
                        'value' => $this->storedTotalShippingCostAsCurrency,
                    ];
                }

                if ($this->storedTotalTaxIncluded > 0) {
                    $miniTable[] = [
                        'label' => t('Tax (inc)', category: 'commerce'),
                        'value' => $this->storedTotalTaxIncludedAsCurrency,
                    ];
                }

                if ($this->storedTotalTax > 0) {
                    $miniTable[] = [
                        'label' => t('Tax', category: 'commerce'),
                        'value' => $this->storedTotalTaxAsCurrency,
                    ];
                }

                if ($this->storedTotalPrice > 0) {
                    $miniTable[] = [
                        'label' => t('Price', category: 'commerce'),
                        'value' => $this->storedTotalPriceAsCurrency,
                    ];
                }

                return $this->_miniTable($miniTable);
            }
            case 'orderSite':
            {
                $site = Sites::getSiteById($this->orderSiteId);
                return Html::encode($site->name ?? '');
            }
            case 'hasAdminNotices':
            {
                if (!$this->hasAdminNotices()) {
                    return '';
                }
                return app(StatusHtml::class)->statusLabelHtml(['color' => 'red', 'label' => t('Yes', category: 'commerce')]);
            }
            default:
            {
                return parent::attributeHtml($attribute);
            }
        }
    }

    #[Override]
    protected static function defineSearchableAttributes(): array
    {
        return [
            'billingFirstName',
            'billingLastName',
            'billingFullName',
            'billingAddress',
            'email',
            'number',
            'shippingFirstName',
            'shippingLastName',
            'shippingFullName',
            'shippingAddress',
            'shortNumber',
            'transactionReference',
            'username',
            'reference',
            'skus',
            'lineItemDescriptions',
            'customerName',
        ];
    }

    #[Override]
    public function getSearchKeywords(string $attribute): string
    {
        switch ($attribute) {
            case 'billingFirstName':
                return $this->billingAddress->firstName ?? '';
            case 'billingLastName':
                return $this->billingAddress->lastName ?? '';
            case 'billingFullName':
                return $this->billingAddress->fullName ?? '';
            case 'billingAddress':
                $address = $this->getBillingAddress();
                return $address ? Addresses::formatAddress($address) : '';
            case 'shippingFirstName':
                return $this->shippingAddress->firstName ?? '';
            case 'shippingLastName':
                return $this->shippingAddress->lastName ?? '';
            case 'shippingFullName':
                return $this->shippingAddress->fullName ?? '';
            case 'shippingAddress':
                $address = $this->getShippingAddress();
                return $address ? Addresses::formatAddress($address) : '';
            case 'transactionReference':
                return implode(' ', ArrayHelper::getColumn($this->getTransactions(), 'reference'));
            case 'username':
                return $this->getCustomer()->username ?? '';
            case 'skus':
                return implode(' ', ArrayHelper::getColumn($this->getLineItems(), 'sku'));
            case 'lineItemDescriptions':
                return implode(' ', ArrayHelper::getColumn($this->getLineItems(), 'description'));
            case 'customerName':
                return $this->getCustomer()->fullName ?? '';
            default:
                return parent::getSearchKeywords($attribute);
        }
    }

    #[Override]
    protected static function defineSources(string $context): array
    {
        $siteHandle = request()->query('site');
        $site = $siteHandle ? Sites::getSiteByHandle($siteHandle) : Sites::getCurrentSite();
        $store = app(Stores::class)->getStoreBySiteId($site->id);
        $orderCriteria = ['isCompleted' => true, 'storeId' => $store->id];

        $sources = [
            '*' => [
                'key' => '*',
                'label' => t('All Orders', category: 'commerce'),
                'criteria' => $orderCriteria,
                'defaultSort' => ['dateOrdered', 'desc'],
                'data' => [
                    'date-attr' => 'dateOrdered',
                ],
            ],
        ];

        $edge = app(Carts::class)->getActiveCartEdgeDuration();

        $criteriaActive = ['dateUpdated' => ['>= ' . $edge], 'isCompleted' => false];
        $criteriaInactive = ['dateUpdated' => ['< ' . $edge], 'isCompleted' => false];
        $criteriaAttemptedPayment = ['hasTransactions' => true, 'isCompleted' => false];

        $orderStatuses = app(OrderStatuses::class)->getAllOrderStatuses($store->id)->all();

        $sources[] = ['heading' => $store->getName()];

        foreach ($orderStatuses as $orderStatus) {
            $key = 'orderStatus:' . $orderStatus->handle;

            $sources[$key] = [
                'key' => $key,
                'status' => $orderStatus->color,
                'label' => t($orderStatus->name, category: 'site'),
                'badgeCount' => 0,
                'criteria' => ArrayHelper::merge($orderCriteria, ['orderStatusId' => $orderStatus->id]),
                'defaultSort' => ['dateOrdered', 'desc'],
                'data' => [
                    'handle' => $orderStatus->handle,
                    'date-attr' => 'dateOrdered',
                ],
            ];
        }

        $sources[] = [
            'key' => 'carts:active:' . $store->handle,
            'label' => t('Active Carts', category: 'commerce'),
            'criteria' => ArrayHelper::merge($criteriaActive, ['storeId' => $store->id]),
            'defaultSort' => ['commerce_orders.dateUpdated', 'asc'],
            'data' => [
                'handle' => 'cartsActive',
                'date-attr' => 'dateUpdated',
            ],
        ];

        $sources[] = [
            'key' => 'carts:inactive:' . $store->handle,
            'label' => t('Inactive Carts', category: 'commerce'),
            'criteria' => ArrayHelper::merge($criteriaInactive, ['storeId' => $store->id]),
            'defaultSort' => ['commerce_orders.dateUpdated', 'desc'],
            'data' => [
                'handle' => 'cartsInactive',
                'date-attr' => 'dateUpdated',
            ],
        ];

        $sources[] = [
            'key' => 'carts:attempted-payment:' . $store->handle,
            'label' => t('Attempted Payments', category: 'commerce'),
            'criteria' => ArrayHelper::merge($criteriaAttemptedPayment, ['storeId' => $store->id]),
            'defaultSort' => ['commerce_orders.dateUpdated', 'desc'],
            'data' => [
                'handle' => 'cartsAttemptedPayment',
                'date-attr' => 'dateUpdated',
            ],
        ];

        return $sources;
    }

    #[Override]
    protected static function defineActions(string $source): array
    {
        $actions = parent::defineActions($source);

        $user = currentUser();

        if ($user?->can('commerce-manageOrders')) {
            $site = app(RequestedSite::class)->get() ?? Sites::getCurrentSite();
            $store = app(Stores::class)->getStoreBySiteId($site->id);
            // Remove nested "all" prefix if it exists at the start of the string
            $source = str_starts_with($source, '*/') ? substr($source, 2) : $source;

            if ($store && app(Pdfs::class)->getHasEnabledPdf($store->id)) {
                $actions[] = ElementActions::createAction([
                    'type' => DownloadOrderPdfAction::class,
                    'storeId' => $store->id,
                ], static::class);
            }

            if ($user->can('commerce-deleteOrders')) {
                $actions[] = ElementActions::createAction([
                    'type' => Delete::class,
                    'confirmationMessage' => t('Are you sure you want to delete the selected orders?', category: 'commerce'),
                    'successMessage' => t('Orders deleted.', category: 'commerce'),
                ], static::class);
            }

            if ($user->can('commerce-editOrders')) {
                // Only allow mass updating order status when all selected are of the same status, and not carts.
                $isStatus = strpos($source, 'orderStatus:');
                if ($isStatus === 0) {
                    $actions[] = ElementActions::createAction([
                        'type' => UpdateOrderStatus::class,
                    ], static::class);
                }

                $isStatus = strpos($source, 'carts:');
                if ($isStatus === 0) {
                    $actions[] = ElementActions::createAction([
                        'type' => CopyLoadCartUrl::class,
                    ], static::class);
                }
            }

            if ($user->can('commerce-deleteOrders')) {
                // Restore
                $actions[] = ElementActions::createAction([
                    'type' => Restore::class,
                    'successMessage' => t('Orders restored.', category: 'commerce'),
                    'partialSuccessMessage' => t('Some orders restored.', category: 'commerce'),
                    'failMessage' => t('Orders not restored.', category: 'commerce'),
                ], static::class);
            }
        }

        return $actions;
    }

    #[Override]
    protected static function defineExporters(string $source): array
    {
        $default = parent::defineExporters($source);
        // Remove the standard expanded exporter and use our own
        ArrayHelper::removeValue($default, CraftExpanded::class);
        $default[] = Expanded::class;

        return $default;
    }

    #[Override]
    protected static function defineTableAttributes(): array
    {
        return array_merge(parent::defineTableAttributes(), [
            'reference' => ['label' => t('Reference', category: 'commerce')],
            'shortNumber' => ['label' => t('Short Number', category: 'commerce')],
            'number' => ['label' => t('Number', category: 'commerce')],
            'id' => ['label' => t('ID', category: 'commerce')],
            'orderStatus' => ['label' => t('Status', category: 'commerce')],
            'totals' => ['label' => t('All Totals', category: 'commerce')],
            'totalQty' => ['label' => t('Total Qty', category: 'commerce')],
            'total' => ['label' => t('Total', category: 'commerce')],
            'totalPrice' => ['label' => t('Total Price', category: 'commerce')],
            'totalPaid' => ['label' => t('Total Paid', category: 'commerce')],
            'totalDiscount' => ['label' => t('Total Discount', category: 'commerce')],
            'totalShippingCost' => ['label' => t('Total Shipping', category: 'commerce')],
            'totalTax' => ['label' => t('Total Tax', category: 'commerce')],
            'totalIncludedTax' => ['label' => t('Total Included Tax', category: 'commerce')],
            'dateOrdered' => ['label' => t('Date Ordered', category: 'commerce')],
            'datePaid' => ['label' => t('Date Paid', category: 'commerce')],
            'dateFirstPaid' => ['label' => t('Date First Paid', category: 'commerce')],
            'dateCreated' => ['label' => t('Date Created', category: 'commerce')],
            'dateUpdated' => ['label' => t('Date Updated', category: 'commerce')],
            'email' => ['label' => t('Email', category: 'commerce')],
            'customer' => ['label' => t('Customer', category: 'commerce')],
            'shippingFullName' => ['label' => t('Shipping Full Name', category: 'commerce')],
            'shippingFirstName' => ['label' => t('Shipping First Name', category: 'commerce')],
            'shippingLastName' => ['label' => t('Shipping Last Name', category: 'commerce')],
            'billingFullName' => ['label' => t('Billing Full Name', category: 'commerce')],
            'billingFirstName' => ['label' => t('Billing First Name', category: 'commerce')],
            'billingLastName' => ['label' => t('Billing Last Name', category: 'commerce')],
            'shippingOrganizationName' => ['label' => t('Shipping Business Name', category: 'commerce')],
            'billingOrganizationName' => ['label' => t('Billing Business Name', category: 'commerce')],
            'shippingMethodName' => ['label' => t('Shipping Method', category: 'commerce')],
            'gatewayName' => ['label' => t('Gateway', category: 'commerce')],
            'paidStatus' => ['label' => t('Paid Status', category: 'commerce')],
            'couponCode' => ['label' => t('Coupon Code', category: 'commerce')],
            'itemTotal' => ['label' => t('Item Total', category: 'commerce')],
            'itemSubtotal' => ['label' => t('Item Subtotal', category: 'commerce')],
            'orderSite' => ['label' => t('Order Site', category: 'commerce')],
            'hasAdminNotices' => ['label' => t('Admin Notices', category: 'commerce')],
        ]);
    }

    #[Override]
    protected static function defineDefaultTableAttributes(string $source): array
    {
        $attributes = [];
        $attributes[] = 'order';

        if (!str_starts_with($source, 'carts:')) {
            // For orders (including order status sources)
            $attributes[] = 'reference';
            if (!str_starts_with($source, 'orderStatus:')) {
                // Only show status column when not filtered by status
                $attributes[] = 'orderStatus';
            }
            $attributes[] = 'customer';
            $attributes[] = 'dateOrdered';
            $attributes[] = 'datePaid';
            $attributes[] = 'dateFirstPaid';
            $attributes[] = 'totalPaid';
            $attributes[] = 'paidStatus';
            $attributes[] = 'totals';
        } else {
            // For carts
            $attributes[] = 'shortNumber';
            $attributes[] = 'dateUpdated';
            $attributes[] = 'totalPrice';
        }

        return $attributes;
    }

    #[Override]
    public static function prepElementQueryForTableAttribute(ElementQueryInterface $elementQuery, string $attribute): void
    {
        /** @var OrderQuery $elementQuery */

        match ($attribute) {
            'totals', 'total', 'totalPrice', 'totalDiscount', 'totalShippingCost', 'totalTax', 'totalIncludedTax' => $elementQuery->withAdjustments(),
            'totalPaid', 'paidStatus' => $elementQuery->withTransactions(),
            'shippingFullName', 'shippingFirstName', 'shippingLastName', 'billingFullName', 'billingFirstName', 'billingLastName', 'shippingOrganizationName', 'billingOrganizationName', 'shippingMethodName' => $elementQuery->withAddresses(),
            'email', 'customer' => $elementQuery->withCustomer(),
            'itemTotal', 'itemSubtotal' => $elementQuery->withLineItems(),
            default => parent::prepElementQueryForTableAttribute($elementQuery, $attribute),
        };
    }

    /**
     * @return ElementConditionInterface
     */
    #[Override]
    public static function createCondition(): ElementConditionInterface
    {
        return new OrderCondition(static::class);
    }

    #[Override]
    protected static function defineSortOptions(): array
    {
        return [
            'number' => t('Number', category: 'commerce'),
            'reference' => t('Reference', category: 'commerce'),
            'orderStatusId' => t('Order Status', category: 'commerce'),
            'totalPrice' => t('Total Price', category: 'commerce'),
            'totalPaid' => t('Total Paid', category: 'commerce'),
            [
                'label' => t('Shipping First Name', category: 'commerce'),
                'orderBy' => 'shipping_address.firstName',
                'attribute' => 'shippingFirstName',
            ],
            [
                'label' => t('Shipping Last Name', category: 'commerce'),
                'orderBy' => 'shipping_address.lastName',
                'attribute' => 'shippingLastName',
            ],
            [
                'label' => t('Shipping Full Name', category: 'commerce'),
                'orderBy' => 'shipping_address.fullName',
                'attribute' => 'shippingFullName',
            ],
            [
                'label' => t('Billing First Name', category: 'commerce'),
                'orderBy' => 'billing_address.firstName',
                'attribute' => 'billingFirstName',
            ],
            [
                'label' => t('Billing Last Name', category: 'commerce'),
                'orderBy' => 'billing_address.lastName',
                'attribute' => 'billingLastName',
            ],
            [
                'label' => t('Billing Full Name', category: 'commerce'),
                'orderBy' => 'billing_address.fullName',
                'attribute' => 'billingFullName',
            ],
            [
                'label' => t('Date Ordered', category: 'commerce'),
                'orderBy' => 'dateOrdered',
                'defaultDir' => 'desc',
            ],
            [
                'label' => t('Date Updated', category: 'commerce'),
                'orderBy' => 'commerce_orders.dateUpdated',
                'attribute' => 'dateUpdated',
                'defaultDir' => 'desc',
            ],
            [
                'label' => t('Date Paid', category: 'commerce'),
                'orderBy' => 'datePaid',
                'defaultDir' => 'desc',
            ],
            [
                'label' => t('Date First Paid', category: 'commerce'),
                'orderBy' => 'dateFirstPaid',
                'defaultDir' => 'desc',
            ],
            'couponCode' => t('Coupon Code', category: 'commerce'),
            [
                'label' => t('ID'),
                'orderBy' => 'elements.id',
                'attribute' => 'id',
            ],
        ];
    }

    /**
     * @param array $miniTable Expects an array with rows of 'label', 'value' keys values.
     */
    private function _miniTable(array $miniTable): string
    {
        $output = '<table style="padding: 0; width: 100%">';
        foreach ($miniTable as $row) {
            $output .= '<tr style="padding: 0">';
            $output .= '<td style="text-align: left; padding: 0px">' . $row['label'] . '</td>';
            $output .= '<td style="text-align: right; padding: 0px">' . $row['value'] . '</td>';
            $output .= '</tr>';
        }
        $output .= '</table>';

        return $output;
    }

    #[Override]
    public static function modifyCustomSource(array $config): array
    {
        try {
            $condition = Conditions::createCondition($config['condition']);
        } catch (\InvalidArgumentException) {
            return $config;
        }

        if (!$condition instanceof OrderCondition) {
            return $config;
        }

        $rules = $condition->getConditionRules();

        // see if it's limited to one product type
        /** @var OrderStatusConditionRule|null $orderStatusConditionRule */
        $orderStatusConditionRule = ArrayHelper::firstWhere($rules, fn($rule) => $rule instanceof OrderStatusConditionRule);
        $orderStatusOptions = $orderStatusConditionRule?->getValues();

        $currentSite = app(RequestedSite::class)->get() ?? Sites::getCurrentSite();
        $store = app(Stores::class)->getStoreBySiteId($currentSite->id);

        if ($orderStatusOptions && count($orderStatusOptions) === 1) {
            $orderStatus = app(OrderStatuses::class)->getOrderStatusByUid(reset($orderStatusOptions));

            if ($store->id != $orderStatus->storeId) {
                $config['disabled'] = true;
            }

            if ($orderStatus) {
                $config['status'] = $orderStatus->color;
            }
        }

        return $config;
    }

    #[Override]
    protected static function defineCardAttributes(): array
    {
        $status = app(OrderStatuses::class)->getAllOrderStatuses()->first();
        $site = Sites::getCurrentSite();
        $number = app(Carts::class)->generateCartNumber();

        return array_merge(parent::defineCardAttributes(), [
            'shortNumber' => [
                'label' => t('Short Number', category: 'commerce'),
                'placeholder' => substr($number, 0, 7),
            ],
            'number' => [
                'label' => t('Number', category: 'commerce'),
                'placeholder' => $number,
            ],
            'id' => [
                'label' => t('ID', category: 'commerce'),
                'placeholder' => '12345',
            ],
            'orderStatus' => [
                'label' => t('Status', category: 'commerce'),
                'placeholder' => $status?->getLabelHtml(),
            ],
            'totalQty' => [
                'label' => t('Total Qty', category: 'commerce'),
                'placeholder' => '10',
            ],
            'total' => [
                'label' => t('Total', category: 'commerce'),
                'placeholder' => '¤' . I18N::getFormatter()->asDecimal(123.99),
            ],
            'totalPrice' => [
                'label' => t('Total Price', category: 'commerce'),
                'placeholder' => '¤' . I18N::getFormatter()->asDecimal(123.99),
            ],
            'totalPaid' => [
                'label' => t('Total Paid', category: 'commerce'),
                'placeholder' => '¤' . I18N::getFormatter()->asDecimal(123.99),
            ],
            'totalDiscount' => [
                'label' => t('Total Discount', category: 'commerce'),
                'placeholder' => '¤' . I18N::getFormatter()->asDecimal(12.99),
            ],
            'totalShippingCost' => [
                'label' => t('Total Shipping', category: 'commerce'),
                'placeholder' => '¤' . I18N::getFormatter()->asDecimal(9.99),
            ],
            'totalTax' => [
                'label' => t('Total Tax', category: 'commerce'),
                'placeholder' => '¤' . I18N::getFormatter()->asDecimal(19.99),
            ],
            'totalIncludedTax' => [
                'label' => t('Total Included Tax', category: 'commerce'),
                'placeholder' => '¤' . I18N::getFormatter()->asDecimal(19.99),
            ],
            'dateOrdered' => [
                'label' => t('Date Ordered', category: 'commerce'),
                'placeholder' => I18N::getFormatter()->asDate(time(), 'short'),
            ],
            'datePaid' => [
                'label' => t('Date Paid', category: 'commerce'),
                'placeholder' => I18N::getFormatter()->asDate(time(), 'short'),
            ],
            'dateFirstPaid' => [
                'label' => t('Date First Paid', category: 'commerce'),
                'placeholder' => I18N::getFormatter()->asDate(time(), 'short'),
            ],
            'dateUpdated' => [
                'label' => t('Date Updated', category: 'commerce'),
                'placeholder' => I18N::getFormatter()->asDate(time(), 'short'),
            ],
            'email' => [
                'label' => t('Email', category: 'commerce'),
                'placeholder' => 'user@example.com',
            ],
            'customer' => [
                'label' => t('Customer', category: 'commerce'),
                'placeholder' => t('Customer', category: 'commerce'),
            ],
            'shippingFullName' => [
                'label' => t('Shipping Full Name', category: 'commerce'),
                'placeholder' => t('Shipping Full Name', category: 'commerce'),
            ],
            'shippingFirstName' => [
                'label' => t('Shipping First Name', category: 'commerce'),
                'placeholder' => t('Shipping First Name', category: 'commerce'),
            ],
            'shippingLastName' => [
                'label' => t('Shipping Last Name', category: 'commerce'),
                'placeholder' => t('Shipping Last Name', category: 'commerce'),
            ],
            'billingFullName' => [
                'label' => t('Billing Full Name', category: 'commerce'),
                'placeholder' => t('Billing Full Name', category: 'commerce'),
            ],
            'billingFirstName' => [
                'label' => t('Billing First Name', category: 'commerce'),
                'placeholder' => t('Billing First Name', category: 'commerce'),
            ],
            'billingLastName' => [
                'label' => t('Billing Last Name', category: 'commerce'),
                'placeholder' => t('Billing Last Name', category: 'commerce'),
            ],
            'shippingOrganizationName' => [
                'label' => t('Shipping Business Name', category: 'commerce'),
                'placeholder' => t('Shipping Business Name', category: 'commerce'),
            ],
            'billingOrganizationName' => [
                'label' => t('Billing Business Name', category: 'commerce'),
                'placeholder' => t('Billing Business Name', category: 'commerce'),
            ],
            'shippingMethodName' => [
                'label' => t('Shipping Method', category: 'commerce'),
                'placeholder' => t('Shipping Method', category: 'commerce'),
            ],
            'gatewayName' => [
                'label' => t('Gateway', category: 'commerce'),
                'placeholder' => t('Gateway', category: 'commerce'),
            ],
            'paidStatus' => [
                'label' => t('Paid Status', category: 'commerce'),
                'placeholder' => app(StatusHtml::class)->statusLabelHtml(['color' => 'green', 'label' => t('Paid', category: 'commerce')]),
            ],
            'couponCode' => [
                'label' => t('Coupon Code', category: 'commerce'),
                'placeholder' => 'SAVE10',
            ],
            'itemTotal' => [
                'label' => t('Item Total', category: 'commerce'),
                'placeholder' => '¤' . I18N::getFormatter()->asDecimal(99.99),
            ],
            'itemSubtotal' => [
                'label' => t('Item Subtotal', category: 'commerce'),
                'placeholder' => '¤' . I18N::getFormatter()->asDecimal(89.99),
            ],
            'orderSite' => [
                'label' => t('Order Site', category: 'commerce'),
                'placeholder' => $site->name,
            ],
            'reference' => [
                'label' => t('Reference', category: 'commerce'),
                'placeholder' => 'ORD-XXXXX',
            ],
        ]);
    }

    #[Override]
    protected static function defineDefaultCardAttributes(): array
    {
        return array_merge(parent::defineDefaultCardAttributes(), [
            'reference',
            'orderStatus',
            'totalPrice',
        ]);
    }

    /**
     * Updates the adjustments, including deleting the old ones.
     */
    private function _saveAdjustments(): void
    {
        $newAdjustmentIds = [];

        foreach ($this->getAdjustments() as $adjustment) {
            try {
                // Don't run validation as validation of the adjustment should happen before saving the order
                app(OrderAdjustments::class)->saveOrderAdjustment($adjustment, false);
            } catch (OrderAdjustmentNotFoundException) {
                // If the adjustment was not found, it means it may have previously existed but was already deleted (race condition).
                // See: https://github.com/craftcms/commerce/issues/3283
                continue;
            }

            $newAdjustmentIds[] = $adjustment->id;
            $adjustment->orderId = $this->id;
        }

        // Make sure all other adjustments have been cleaned up.
        DB::table(Table::ORDERADJUSTMENTS)
            ->where('orderId', $this->id)
            ->whereNotIn('id', $newAdjustmentIds)
            ->delete();
    }

    private function _saveNotices(): void
    {
        $previousNoticeIds = OrderNoticeRecord::find()->select(['id'])->where(['orderId' => $this->id])->column();

        $currentNoticeIds = [];

        // We are never updating a notice, just adding it or keeping it.
        foreach (array_merge($this->getNotices(), $this->getAdminNotices()) as $notice) {
            if ($notice->id === null) {
                $orderNoticeEvent = new OrderNoticeEvent(
                    orderNotice: $notice,
                );

                // Raising the 'beforeAddNoticeToOrder' event
                if ($this->hasEventHandlers(self::EVENT_BEFORE_APPLY_ADD_NOTICE)) {
                    $this->trigger(self::EVENT_BEFORE_APPLY_ADD_NOTICE, $orderNoticeEvent);

                    if ($orderNoticeEvent->isValid === false) {
                        continue;
                    }
                }
                $noticeRecord = new OrderNoticeRecord();
                $noticeRecord->orderId = $notice->orderId;
                $noticeRecord->type = $notice->type;
                $noticeRecord->attribute = $notice->attribute;
                $noticeRecord->message = $notice->message;
                $noticeRecord->noticeType = $notice->noticeType->value;
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
     */
    private function _saveLineItems(): void
    {
        // Line items that are currently in the DB
        $previousLineItems = $this->id ? app(LineItems::class)->getAllLineItemsByOrderId($this->id) : [];

        $currentLineItemIds = [];

        // Determine the line items that will be saved
        foreach ($this->getLineItems() as $lineItem) {
            // If the ID is null that's ok, it's a new line item and will be saved anyway
            $currentLineItemIds[] = $lineItem->id;
        }

        // Delete any line items that no longer will be saved on this order.
        foreach ($previousLineItems as $previousLineItem) {
            if (!in_array($previousLineItem->id, $currentLineItemIds, false)) {
                DB::table(Table::LINEITEMS)->where('id', $previousLineItem->id)->delete();

                if ($this->hasEventHandlers(self::EVENT_AFTER_APPLY_REMOVE_LINE_ITEM)) {
                    $this->trigger(self::EVENT_AFTER_APPLY_REMOVE_LINE_ITEM, new LineItemEvent(
                        lineItem: $previousLineItem,
                    ));
                }
            }
        }

        // Save the line items last, as we know that any possible duplicates are already removed.
        // We also need to re-save any adjustments that didn't have a line item ID for a line item if it's new.
        foreach ($this->getLineItems() as $lineItem) {
            $originalId = $lineItem->id;
            $lineItem->setOrder($this); // just in case.

            try {
                // Don't run validation as validation of the line item should happen before saving the order
                app(LineItems::class)->saveLineItem($lineItem, false);
            } catch (LineItemNotFoundException) {
                // If the line item was not found, it means it may have previously existed but was already deleted (race condition).
                // See: https://github.com/craftcms/commerce/issues/3283
                continue;
            }

            // Is this a new line item?
            if ($originalId === null) {
                // Raising the 'afterAddLineItemToOrder' event
                if ($this->hasEventHandlers(self::EVENT_AFTER_APPLY_ADD_LINE_ITEM)) {
                    $this->trigger(self::EVENT_AFTER_APPLY_ADD_LINE_ITEM, new LineItemEvent(
                        lineItem: $lineItem,
                        isNew: true,
                    ));
                }
            }

            // Update any adjustments to this line item with the new line item ID.
            foreach ($this->getAdjustments() as $adjustment) {
                // Was the adjustment for this line item, but the line item ID didn't exist when the adjustment was made?
                if ($adjustment->getLineItem() === $lineItem && !$adjustment->lineItemId) {
                    // Re-save the adjustment with the new line item ID, since it exists now.
                    $adjustment->lineItemId = $lineItem->id;
                    // Validation not needed as the adjustments are validated before the order is saved
                    try {
                        app(OrderAdjustments::class)->saveOrderAdjustment($adjustment, false);
                    } catch (OrderAdjustmentNotFoundException) {
                        // This can happen if the adjustment was removed during a race condition recalculation.
                        continue;
                    }
                }
            }
        }
    }

    /**
     * Delete all addresses that are owned by the order but are not in use.
     */
    private function _deleteOrphanedOrderAddresses(): void
    {
        if (!$this->id) {
            return;
        }

        $safeIds = array_filter([
            $this->getBillingAddress()?->id,
            $this->getShippingAddress()?->id,
            $this->getEstimatedBillingAddress()?->id,
            $this->getEstimatedShippingAddress()?->id,
        ]);

        $orphanedAddresses = AddressElement::find()
            ->ownerId($this->id);

        if (!empty($safeIds)) {
            ArrayHelper::prependOrAppend($safeIds, 'not', true);
            $orphanedAddresses->id($safeIds);
        }

        ($orphanedAddresses->collect())->each(function(AddressElement $address) {
            Elements::deleteElement($address, true);
        });
    }

    private function _saveOrderHistory(?int $oldStatusId, ?int $currentOrderStatId): void
    {
        $hasNewStatus = ($oldStatusId !== $currentOrderStatId);
        if ($this->isCompleted && $hasNewStatus) {
            if (!app(OrderHistories::class)->createOrderHistoryFromOrder($this, $oldStatusId)) {
                Log::error('Error saving order history after order save.');
            }
        }
    }

    /**
     * Sets the first and last name attributes on the address model if no full name is set.
     */
    private function _populateAddressNameAttributes(AddressElement $addressElement, array $address): void
    {
        if (!isset($address['fullName']) || !$address['fullName']) {
            $firstName = $address['firstName'] ?? null;
            $lastName = $address['lastName'] ?? null;

            if ($firstName !== null || $lastName !== null) {
                $addressElement->fullName = null;
                $addressElement->firstName = $firstName ?? $addressElement->firstName;
                $addressElement->lastName = $lastName ?? $addressElement->lastName;
            }
        }
    }
}
