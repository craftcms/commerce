<?php

namespace craft\commerce\elements;

use Craft;
use craft\commerce\base\Element;
use craft\commerce\base\ShippingMethodInterface;
use craft\commerce\elements\db\OrderQuery;
use craft\commerce\helpers\Currency;
use craft\commerce\models\Address;
use craft\commerce\models\Customer;
use craft\commerce\models\LineItem;
use craft\commerce\models\OrderAdjustment;
use craft\commerce\models\OrderHistory;
use craft\commerce\models\OrderSettings;
use craft\commerce\models\OrderStatus;
use craft\commerce\models\PaymentMethod;
use craft\commerce\models\Transaction;
use craft\commerce\Plugin;
use craft\elements\db\ElementQueryInterface;
use craft\models\FieldLayout;

/**
 * Order or Cart model.
 *
 * @property int                     $id
 * @property string                  $number
 * @property string                  $couponCode
 * @property float                   $itemTotal
 * @property float                   $totalPrice
 * @property float                   $totalPaid
 * @property float                   $baseDiscount
 * @property float                   $baseShippingCost
 * @property float                   $baseTax
 * @property string                  $email
 * @property bool                    $isCompleted
 * @property \DateTime               $dateOrdered
 * @property string                  $currency
 * @property string                  $paymentCurrency
 * @property \DateTime               $datePaid
 * @property string                  $lastIp
 * @property string                  $orderLocale
 * @property string                  $message
 * @property string                  $returnUrl
 * @property string                  $cancelUrl
 *
 * @property int                     $billingAddressId
 * @property int                     $shippingAddressId
 * @property ShippingMethodInterface $shippingMethod
 * @property int                     $paymentMethodId
 * @property int                     $customerId
 * @property int                     $orderStatusId
 *
 * @property int                     $totalQty
 * @property int                     $totalWeight
 * @property int                     $totalHeight
 * @property int                     $totalLength
 * @property int                     $totalWidth
 * @property int                     $totalTax
 * @property int                     $totalShippingCost
 * @property int                     $totalDiscount
 * @property string                  $pdfUrl
 *
 * @property OrderSettings           $type
 * @property LineItem[]              $lineItems
 * @property Address                 $billingAddress
 * @property Customer                $customer
 * @property Address                 $shippingAddress
 * @property OrderAdjustment[]       $adjustments
 * @property PaymentMethod           $paymentMethod
 * @property Transaction[]           $transactions
 * @property OrderStatus             $orderStatus
 * @property OrderHistory[]          $histories
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.models
 * @since     1.0
 */
class Order extends Element
{

    /**
     * @var int ID
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
     * @var float Item Total
     */
    public $itemTotal = 0;

    /**
     * @var float Base Discount
     */
    public $baseDiscount = 0;

    /**
     * @var float Base Shipping Cost
     */
    public $baseShippingCost = 0;

    /**
     * @var float Base Tax
     */
    public $baseTax = 0;

    /**
     * @var float Total Price
     */
    public $totalPrice = 0;

    /**
     * @var float Total Paid
     */
    public $totalPaid = 0;

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
    public $shippingMethod;

    /**
     * @var int Payment Method ID
     */
    public $paymentMethodId;

    /**
     * @var int Customer ID
     */
    public $customerId;

    /**
     * @var
     */
    private $_shippingAddress;

    /**
     * @var
     */
    private $_billingAddress;

    /**
     * @var array
     */
    private $_lineItems;

    /**
     * @var array
     */
    private $_orderAdjustments;

    public function afterSave(bool $isNew)
    {
        if ($isNew) {
//            Craft::$app->db->createCommand()
//                ->insert('{{%products}}', [
//                    'id' => $this->id,
//                    'price' => $this->price,
//                    'currency' => $this->currency,
//                ])
//                ->execute();
        } else {
//            Craft::$app->db->createCommand()
//                ->update('{{%products}}', [
//                    'price' => $this->price,
//                    'currency' => $this->currency,
//                ], ['id' => $this->id])
//                ->execute();
        }

        parent::afterSave($isNew);
    }

    /**
     * @inheritdoc
     *
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
    public static function hasTitles(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public static function hasStatuses(): bool
    {
        return false;
    }

    /**
     * @return bool
     */
    public static function isLocalized(): bool
    {
        return false;
    }

    /**
     * @return array
     */
    public static function defineSearchableAttributes(): array
    {
        return ['number', 'email'];
    }

    /**
     * @return bool
     */
    public function isEditable()
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
    public function __toString()
    {
        return $this->getShortNumber();
    }

    /**
     * @return string
     */
    public function getShortNumber()
    {
        return substr($this->number, 0, 7);
    }

    /**
     * @inheritdoc
     * @return string
     */
    public function getLink()
    {
        return TemplateHelper::getRaw("<a href='".$this->getCpEditUrl()."'>".substr($this->number, 0, 7)."</a>");
    }

    /**
     * @inheritdoc
     * @return string
     */
    public function getCpEditUrl()
    {
        return UrlHelper::getCpUrl('commerce/orders/'.$this->id);
    }

    /**
     * Returns the URL to the order’s PDF invoice.
     *
     * @param string|null $option The option that should be available to the PDF template (e.g. “receipt”)
     *
     * @return string|null The URL to the order’s PDF invoice, or null if the PDF template doesn’t exist
     */
    public function getPdfUrl($option = null)
    {
        $url = null;

        // Make sure the template exists
        $template = Plugin::getInstance()->getSettings()->getSettings()->orderPdfPath;

        if ($template) {
            // Set Craft to the site template mode
            $templatesService = Craft::$app->getView();
            $oldTemplateMode = $templatesService->getTemplateMode();
            $templatesService->setTemplateMode(TemplateMode::Site);

            if ($templatesService->doesTemplateExist($template)) {
                $url = UrlHelper::getActionUrl("commerce/downloads/pdf?number={$this->number}".($option ? "&option={$option}" : null));
            }

            // Restore the original template mode
            $templatesService->setTemplateMode($oldTemplateMode);
        }

        return $url;
    }

    /**
     * @return FieldLayout
     */
    public function getFieldLayout()
    {
        /** @var OrderSettings $orderSettings */
        $orderSettings = Plugin::getInstance()->getOrderSettings()->getOrderSettingByHandle('order');

        if ($orderSettings) {
            return $orderSettings->getFieldLayout();
        }
    }

    /**
     * Whether or not this order is made by a guest user.
     *
     * @return bool
     */
    public function isGuest()
    {
        if ($this->getCustomer()) {
            return (bool)!$this->getCustomer()->userId;
        }

        return true;
    }

    /**
     * @return \craft\commerce\models\Customer|null
     */
    public function getCustomer()
    {
        if ($this->customerId) {
            return Plugin::getInstance()->getCustomers()->getCustomerById($this->customerId);
        }
    }

    /**
     * @return bool
     */
    public function isPaid()
    {
        return (bool)$this->outstandingBalance() <= 0;
    }

    /**
     * Returns the difference between the order amount and amount paid.
     *
     * @return float
     */
    public function outstandingBalance()
    {

        $totalPaid = Currency::round($this->totalPaid);
        $totalPrice = Currency::round($this->totalPrice);

        return $totalPrice - $totalPaid;
    }

    /**
     * @return bool
     */
    public function isUnpaid()
    {
        return (bool)$this->outstandingBalance() > 0;
    }

    /**
     * Is this order the users current active cart.
     *
     * @return bool
     */
    public function isActiveCart()
    {
        $cart = Plugin::getInstance()->getCart()->getCart();

        return ($cart && $cart->id == $this->id);
    }

    /**
     * Has the order got any items in it?
     *
     * @return bool
     */
    public function isEmpty()
    {
        return $this->getTotalQty() == 0;
    }

    /**
     * Total number of items.
     *
     * @return int
     */
    public function getTotalQty()
    {
        $qty = 0;
        foreach ($this->getLineItems() as $item) {
            $qty += $item->qty;
        }

        return $qty;
    }

    /**
     * @return \craft\commerce\elements\LineItem[]
     */
    public function getLineItems()
    {
        if (null === $this->_lineItems) {
            $this->setLineItems(Plugin::getInstance()->getLineItems()->getAllLineItemsByOrderId($this->id));
        }

        return $this->_lineItems;
    }

    /**
     * @param \craft\commerce\elements\LineItem[] $lineItems
     */
    public function setLineItems($lineItems)
    {
        $this->_lineItems = $lineItems;
    }

    /**
     * @return float
     */
    public function getTotalTax()
    {
        $tax = 0;
        foreach ($this->getLineItems() as $item) {
            $tax += $item->tax;
        }

        return $tax + $this->baseTax;
    }

    /**
     * @return float
     */
    public function getTotalTaxIncluded()
    {
        $tax = 0;
        foreach ($this->getLineItems() as $item) {
            $tax += $item->taxIncluded;
        }

        return $tax;
    }

    /**
     * @return float
     */
    public function getTotalDiscount()
    {
        $discount = 0;
        foreach ($this->getLineItems() as $item) {
            $discount += $item->discount;
        }

        return $discount + $this->baseDiscount;
    }

    /**
     * @return float
     */
    public function getTotalShippingCost()
    {
        $shippingCost = 0;
        foreach ($this->getLineItems() as $item) {
            $shippingCost += $item->shippingCost;
        }

        return $shippingCost + $this->baseShippingCost;
    }

    /**
     * @return int
     */
    public function getTotalWeight()
    {
        $weight = 0;
        foreach ($this->getLineItems() as $item) {
            $weight += ($item->qty * $item->weight);
        }

        return $weight;
    }

    /**
     * @return int
     */
    public function getTotalLength()
    {
        $value = 0;
        foreach ($this->getLineItems() as $item) {
            $value += ($item->qty * $item->length);
        }

        return $value;
    }

    /**
     * @return int
     */
    public function getTotalWidth()
    {
        $value = 0;
        foreach ($this->getLineItems() as $item) {
            $value += ($item->qty * $item->width);
        }

        return $value;
    }

    /**
     * Returns the total sale amount.
     *
     * @return int
     */
    public function getTotalSaleAmount()
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
     * @return int
     */
    public function getItemSubtotal()
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
    public function getAdjustmentSubtotal()
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
     * @return \craft\commerce\models\OrderAdjustment[]
     */
    public function getAdjustments()
    {
        if (!$this->_orderAdjustments) {
            $this->_orderAdjustments = Plugin::getInstance()->getOrderAdjustments()->getAllOrderAdjustmentsByOrderId($this->id);
        }

        return $this->_orderAdjustments;
    }

    /**
     * @return int
     */
    public function getTotalHeight()
    {
        $value = 0;
        foreach ($this->getLineItems() as $item) {
            $value += $item->qty * $item->height;
        }

        return $value;
    }

    /**
     * @param \craft\commerce\models\OrderAdjustment[] $adjustments
     */
    public function setAdjustments($adjustments)
    {
        $this->_orderAdjustments = $adjustments;
    }

    /**
     * @return \craft\commerce\models\Address
     */
    public function getShippingAddress()
    {
        if (null === $this->_shippingAddress) {
            $this->_shippingAddress = Plugin::getInstance()->getAddresses()->getAddressById($this->shippingAddressId);
        }

        return $this->_shippingAddress;
    }

    /**
     * @param \craft\commerce\models\Address $address
     */
    public function setShippingAddress(\craft\commerce\models\Address $address)
    {
        $this->_shippingAddress = $address;
    }

    /**
     * @return \craft\commerce\models\Address
     */
    public function getBillingAddress()
    {
        if (null === $this->_billingAddress) {
            $this->_billingAddress = Plugin::getInstance()->getAddresses()->getAddressById($this->billingAddressId);
        }

        return $this->_billingAddress;
    }

    /**
     *
     * @param \craft\commerce\models\Address $address
     */
    public function setBillingAddress(\craft\commerce\models\Address $address)
    {
        $this->_billingAddress = $address;
    }

    /**
     * @return ShippingMethodInterface|null
     */
    public function getShippingMethodId()
    {
        if ($this->getShippingMethod()) {
            return $this->getShippingMethod()->getId();
        };
    }

    /**
     * @return int|null
     */
    public function getShippingMethod()
    {
        return Plugin::getInstance()->getShippingMethods()->getShippingMethodByHandle($this->getShippingMethodHandle());
    }

    /**
     * @return string|null
     */
    public function getShippingMethodHandle()
    {
        return $this->getAttribute('shippingMethod');
    }

    /**
     * @return PaymentMethod|null
     */
    public function getPaymentMethod()
    {
        return Plugin::getInstance()->getPaymentMethods()->getPaymentMethodById($this->getAttribute('paymentMethodId'));
    }

    /**
     * @return OrderHistory[]
     */
    public function getHistories()
    {
        return Plugin::getInstance()->getOrderHistories()->getAllOrderHistoriesByOrderId($this->id);
    }

    // Original Element Methods:

    /**
     * @return Transaction[]
     */
    public function getTransactions()
    {
        return Plugin::getInstance()->getTransactions()->getAllTransactionsByOrderId($this->id);
    }

    /**
     * @return \craft\commerce\models\OrderStatus|null
     */
    public function getOrderStatus()
    {
        return Plugin::getInstance()->getOrderStatuses()->getOrderStatusById($this->orderStatusId);
    }

    /**
     * @return null|string
     */
    public function getName()
    {
        return Craft::t('commerce', 'Orders');
    }

    /**
     * @param null $source
     *
     * @return array
     */
    public function getAvailableActions($source = null): array
    {
        $actions = [];

        if (Craft::$app->getUser()->checkPermission('commerce-manageOrders')) {
            $deleteAction = Craft::$app->getElements()->getAction('Delete');
            $deleteAction->setParams([
                'confirmationMessage' => Craft::t('commerce', 'Are you sure you want to delete the selected orders?'),
                'successMessage' => Craft::t('commerce', 'Orders deleted.'),
            ]);
            $actions[] = $deleteAction;

            // Only allow mass updating order status when all selected are of the same status, and not carts.
            $isStatus = strpos($source, 'orderStatus:');
            if ($isStatus === 0) {
                $updateOrderStatusAction = Craft::$app->getElements()->getAction('UpdateOrderStatus');
                $actions[] = $updateOrderStatusAction;
            }
        }

        // Allow plugins to add additional actions
        // TODO Update to events for Commerce 2
//        $allPluginActions = Craft::$app->getPlugins()->call('commerce_addOrderActions', [$source], true);
//
//        foreach ($allPluginActions as $pluginActions) {
//            $actions = array_merge($actions, $pluginActions);
//        }

        return $actions;
    }

    /**
     * @param string|null $context
     *
     * @return array
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

        $sources[] = ['heading' => Craft::t("commerce", "Order Status")];

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

        $sources[] = ['heading' => Craft::t("commerce", "Carts")];

        $edge = new \DateTime();
        $interval = new \DateInterval("PT1H");
        $interval->invert = 1;
        $edge->add($interval);

        $sources[] = [
            'key' => 'carts:active',
            'label' => Craft::t('commerce', 'Active Carts'),
            'criteria' => ['updatedAfter' => $edge->getTimestamp(), 'isCompleted' => 'not 1'],
            'defaultSort' => ['orders.dateUpdated', 'asc']
        ];

        $sources[] = [
            'key' => 'carts:inactive',
            'label' => Craft::t('commerce', 'Inactive Carts'),
            'criteria' => ['updatedBefore' => $edge->getTimestamp(), 'isCompleted' => 'not 1'],
            'defaultSort' => ['orders.dateUpdated', 'desc']
        ];

        // Allow plugins to modify the sources
        // TODO Make this an event for Commerce 2
//        Craft::$app->getPlugins()->call('commerce_modifyOrderSources', [&$sources, $context]);

        return $sources;
    }

    /**
     * @return array
     */
    public static function defineTableAttributes(): array
    {
        $attributes = [
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
            'paymentMethodName' => ['label' => Craft::t('commerce', 'Payment Method')]
        ];

        // Allow plugins to modify the attributes
        // TODO switch to an event in Commerce 2
        //$pluginAttributes = Craft::$app->getPlugins()->call('commerce_defineAdditionalOrderTableAttributes', [], true);

//        foreach ($pluginAttributes as $thisPluginAttributes) {
//            $attributes = array_merge($attributes, $thisPluginAttributes);
//        }

        return $attributes;
    }

    /**
     * @param string|null $source
     *
     * @return array
     */
    public static function defineDefaultTableAttributes(string $source = null): array
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
     * @param string $attribute
     *
     * @return mixed|string
     */
    public function tableAttributeHtml(string $attribute): string
    {
        switch ($attribute) {
            case 'orderStatus': {
                if ($this->orderStatus) {
                    return $this->orderStatus->htmlLabel();
                }

                return '<span class="status"></span>';
            }
            case 'shippingFullName': {
                if ($this->shippingAddress) {
                    return $this->shippingAddress->getFullName();
                }

                return '';
            }
            case 'billingFullName': {
                if ($this->billingAddress) {
                    return $this->billingAddress->getFullName();
                }

                return '';
            }
            case 'shippingBusinessName': {
                if ($this->shippingAddress) {
                    return $this->shippingAddress->businessName;
                }

                return '';
            }
            case 'billingBusinessName': {
                if ($this->billingAddress) {
                    return $this->billingAddress->businessName;
                }

                return '';
            }
            case 'shippingMethodName': {
                if ($this->shippingMethod) {
                    return $this->shippingMethod->getName();
                }

                return '';
            }
            case 'paymentMethodName': {
                if ($this->paymentMethod) {
                    return $this->paymentMethod->name;
                }

                return '';
            }
            case 'totalPaid':
            case 'totalPrice':
            case 'totalShippingCost':
            case 'totalDiscount': {

                if ($this->$attribute == 0) {
                    return '';
                }

                if ($this->$attribute > 0) {
                    return craft()->numberFormatter->formatCurrency($this->$attribute, $this->currency);
                }

                return craft()->numberFormatter->formatCurrency($this->$attribute * -1, $this->currency);
            }
            default: {
                return parent::tableAttributeHtml($attribute);
            }
        }
    }

    /**
     * @return array
     */
    public function defineSortableAttributes(): array
    {
        $attributes = [
            'number' => Craft::t('commerce', 'Number'),
            'id' => Craft::t('commerce', 'ID'),
            'orderStatusId' => Craft::t('commerce', 'Order Status'),
            'totalPrice' => Craft::t('commerce', 'Total Payable'),
            'totalPaid' => Craft::t('commerce', 'Total Paid'),
            'dateOrdered' => Craft::t('commerce', 'Date Ordered'),
            'orders.dateUpdated' => Craft::t('commerce', 'Date Updated'),
            'datePaid' => Craft::t('commerce', 'Date Paid')
        ];

        // Allow plugins to modify the attributes
        // TODO update to events for Commerce 2
        //Craft::$app->getPlugins()->call('commerce_modifyOrderSortableAttributes', [&$attributes]);

        return $attributes;
    }

    /**
     * Populate the Order.
     *
     * @param array $row
     *
     * @return Element
     */
    public function populateElementModel($row): Element
    {
        return new Order($row);
    }

}
