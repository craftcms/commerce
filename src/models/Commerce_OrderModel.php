<?php
namespace Craft;

use Commerce\Helpers\CommerceCurrencyHelper;

/**
 * Order or Cart model.
 *
 * @property int $id
 * @property string $number
 * @property string $couponCode
 * @property float $itemTotal
 * @property float $totalPrice
 * @property float $totalPaid
 * @property float $baseDiscount
 * @property float $baseShippingCost
 * @property float $baseTax
 * @property float $baseTaxIncluded
 * @property string $email
 * @property bool $isCompleted
 * @property DateTime $dateOrdered
 * @property string $currency
 * @property string $paymentCurrency
 * @property DateTime $datePaid
 * @property string $lastIp
 * @property string $orderLocale
 * @property string $message
 * @property string $returnUrl
 * @property string $cancelUrl
 *
 * @property int $billingAddressId
 * @property int $shippingAddressId
 * @property int $shippingMethod
 * @property int $paymentMethodId
 * @property int $customerId
 * @property int $orderStatusId
 *
 * @property int $totalQty
 * @property int $totalWeight
 * @property int $totalHeight
 * @property int $totalLength
 * @property int $totalWidth
 * @property int $totalTax
 * @property int $totalShippingCost
 * @property int $totalDiscount
 * @property string $pdfUrl
 *
 * @property Commerce_OrderSettingsModel $type
 * @property Commerce_LineItemModel[] $lineItems
 * @property Commerce_AddressModel $billingAddress
 * @property Commerce_CustomerModel $customer
 * @property Commerce_AddressModel $shippingAddress
 * @property Commerce_OrderAdjustmentModel[] $adjustments
 * @property Commerce_PaymentMethodModel $paymentMethod
 * @property Commerce_TransactionModel[] $transactions
 * @property Commerce_OrderStatusModel $orderStatus
 * @property Commerce_OrderHistoryModel[] $histories
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.models
 * @since     1.0
 */
class Commerce_OrderModel extends BaseElementModel
{
    /**
     * @var string
     */
    protected $elementType = 'Commerce_Order';

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

    /**
     * @var bool $_recalcuate
     */
    private $_recalcuate = true;

    /**
     * @var string $_email
     */
    private $_email;

    /**
     * We need to have getters functions have maximum priority.
     * This was in the ModelRelationTrait so it needs to stay for backwards compatibility.
     * @param string $name
     *
     * @return mixed
     */
    public function __get($name)
    {
        $getter = 'get'.$name;
        if (method_exists($this, $getter))
        {
            return $this->$getter();
        }

        return parent::__get($name);
    }

    /**
     * @return bool
     */
    public function isEditable()
    {
        // Still a cart, allow full editing.
        if(!$this->isCompleted){
            return true;
        }else{
            return craft()->userSession->checkPermission('commerce-manageOrders');
        }
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->getShortNumber();
    }

    /**
     * @return bool Should this order recalculate before being saved
     */
    public function getShouldRecalculateAdjustments()
    {
        return (bool) (!$this->isCompleted && $this->_recalcuate);
    }

    /**
     * @param bool $value
     *
     * @return void
     */
    public function setShouldRecalculateAdjustments($value)
    {
        $this->_recalcuate = $value;
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
        return TemplateHelper::getRaw("<a href='" . $this->getCpEditUrl() . "'>" . substr($this->number, 0, 7) . "</a>");
    }

    /**
     * @inheritdoc
     * @return string
     */
    public function getCpEditUrl()
    {
        return UrlHelper::getCpUrl('commerce/orders/' . $this->id);
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
        $template = craft()->commerce_settings->getSettings()->orderPdfPath;

        if ($template)
        {
            // Set Craft to the site template mode
            $templatesService = craft()->templates;
            $oldTemplateMode = $templatesService->getTemplateMode();
            $templatesService->setTemplateMode(TemplateMode::Site);

            if ($templatesService->doesTemplateExist($template))
            {
                $url = UrlHelper::getActionUrl("commerce/downloads/pdf?number={$this->number}".($option ? "&option={$option}" : null));
            }

            // Restore the original template mode
            $templatesService->setTemplateMode($oldTemplateMode);
        }

        return $url;
    }

    /**
     * @return FieldLayoutModel
     */
    public function getFieldLayout()
    {
        /** @var Commerce_OrderSettingsModel $orderSettings */
        $orderSettings = craft()->commerce_orderSettings->getOrderSettingByHandle('order');

        if ($orderSettings)
        {
            return $orderSettings->getFieldLayout();
        }
    }

    /**
     * @return bool
     */
    public function isLocalized()
    {
        return false;
    }

    /**
     * @return Commerce_CustomerModel|null
     */
    public function getCustomer()
    {
        if($this->customerId){
            return craft()->commerce_customers->getCustomerById($this->customerId);
        }
    }

    /**
     * Whether or not this order is made by a guest user.
     * @return bool
     */
    public function isGuest()
    {
        if($this->getCustomer()){
            return (bool) !$this->getCustomer()->userId;
        }

        return true;
    }

    /**
     * @return bool
     */
    public function isPaid()
    {
        return (bool) max(0, $this->outstandingBalance() <= 0);
    }

    /**
     * @return bool
     */
    public function isUnpaid()
    {
        return (bool) max(0, $this->outstandingBalance() > 0);
    }

    /**
     * Returns the difference between the order amount and amount paid.
     *
     * @return float
     */
    public function outstandingBalance()
    {
        $totalPaid = CommerceCurrencyHelper::round($this->totalPaid);
        $totalPrice = CommerceCurrencyHelper::round($this->totalPrice);
        
        return $totalPrice - $totalPaid;
    }

    /**
     * Is this order the users current active cart.
     * @return bool
     */
    public function isActiveCart()
    {
        $cart = craft()->commerce_cart->getCart();

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
    public function getTotalTaxablePrice()
    {
        return $this->getItemSubtotal() + $this->getTotalDiscount() + $this->getTotalShippingCost();
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

        return $tax + $this->baseTaxIncluded;
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
     * @return int
     */
    public function getItemSubtotalWithSale()
    {
        craft()->deprecator->log('Commerce_OrderModel::getItemSubtotalWithSale():removed', 'You should no longer use `order.itemSubtotalWithSale` for the line item’s subtotal. Use `order.itemSubtotal`. Same goes for order->getItemSubtotalWithSale() in PHP.');

        return $this->getItemSubtotal();
    }

    /**
     * Returns the total of all line item's subtotals.
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
     * @return float|int
     */
    public function getAdjustmentSubtotal()
    {
        $value = 0;
        foreach ($this->getAdjustments() as $adjustment) {
            if (!$adjustment->included)
            {
                $value += $adjustment->amount;
            }
        }

        return $value;
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
     * @return Commerce_LineItemModel[]
     */
    public function getLineItems()
    {
        if (!isset($this->_lineItems)){
            $this->setLineItems(craft()->commerce_lineItems->getAllLineItemsByOrderId($this->id));
        }

        return $this->_lineItems;
    }

    /**
     * @param Commerce_LineItemModel[] $lineItems
     */
    public function setLineItems($lineItems)
    {
        $this->_lineItems = $lineItems;
    }

    /**
     * @param Commerce_OrderAdjustmentModel[] $adjustments
     */
    public function setAdjustments($adjustments)
    {
        $this->_orderAdjustments = $adjustments;
    }

    /**
     * @return Commerce_OrderAdjustmentModel[]
     */
    public function getAdjustments()
    {
        if(!$this->_orderAdjustments){
            $this->_orderAdjustments = craft()->commerce_orderAdjustments->getAllOrderAdjustmentsByOrderId($this->id);
        }

        return $this->_orderAdjustments;
    }

    /**
     * @return Commerce_AddressModel
     */
    public function getShippingAddress()
    {
        if (!isset($this->_shippingAddress)) {
            $this->_shippingAddress = craft()->commerce_addresses->getAddressById($this->shippingAddressId);
        }

        return $this->_shippingAddress;
    }

    /**
     * @param Commerce_AddressModel $address
     */
    public function setShippingAddress(Commerce_AddressModel $address)
    {
        $this->_shippingAddress = $address;
    }

    /**
     * @return Commerce_AddressModel
     */
    public function getBillingAddress()
    {
        if (!isset($this->_billingAddress)) {
            $this->_billingAddress = craft()->commerce_addresses->getAddressById($this->billingAddressId);
        }

        return $this->_billingAddress;
    }

    /**
     *
     * @param Commerce_AddressModel $address
     */
    public function setBillingAddress(Commerce_AddressModel $address)
    {
        $this->_billingAddress = $address;
    }

    /**
     * @return \Commerce\Interfaces\ShippingMethod|null
     */
    public function getShippingMethodId()
    {
        if($this->getShippingMethod()){
            return $this->getShippingMethod()->getId();
        };
    }

    /**
     * @return string|null
     */
    public function getShippingMethodHandle()
    {
        return $this->getAttribute('shippingMethod');
    }

    /**
     * @return int|null
     */
    public function getShippingMethod()
    {
        return craft()->commerce_shippingMethods->getShippingMethodByHandle($this->getShippingMethodHandle());
    }

    /**
     * @return Commerce_PaymentMethodModel|null
     */
    public function getPaymentMethod()
    {
        return craft()->commerce_paymentMethods->getPaymentMethodById($this->getAttribute('paymentMethodId'));
    }

    /**
     * @return Commerce_OrderHistoryModel[]
     */
    public function getHistories()
    {
        return craft()->commerce_orderHistories->getAllOrderHistoriesByOrderId($this->id);
    }

    /**
     * @return Commerce_TransactionModel[]
     */
    public function getTransactions()
    {
        return craft()->commerce_transactions->getAllTransactionsByOrderId($this->id);
    }

    /**
     * @return string
     */
    public function getEmail()
    {
        if ($this->getCustomer() && $this->getCustomer()->getUser())
        {
            $this->setEmail($this->getCustomer()->getUser()->email);
        }

        return $this->_email;
    }

    /**
     * @param $value
     */
    public function setEmail($value)
    {
        $this->_email = $value;
    }

    /**
     * @param mixed $row
     *
     * @return Commerce_OrderModel
     */
    public static function populateModel($row)
    {
        $model = parent::populateModel($row);
        $model->setEmail($row['email']);
        return $model;
    }

    /**
     * @deprecated
     * @return bool
     */
    public function showAddress()
    {
        craft()->deprecator->log('Commerce_OrderModel::showAddress():removed', 'You should no longer use `cart.showAddress` in twig to determine whether to show the address form. Do your own check in twig like this `{% if cart.linItems|length > 0 %}`');

        return count($this->getLineItems()) > 0;
    }

    /**
     * @deprecated
     * @return bool
     */
    public function showPayment()
    {
        craft()->deprecator->log('Commerce_OrderModel::showPayment():removed', 'You should no longer use `cart.showPayment` in twig to determine whether to show the payment form. Do your own check in twig like this `{% if cart.linItems|length > 0 and cart.billingAddressId and cart.shippingAddressId %}`');

        return count($this->getLineItems()) > 0 && $this->billingAddressId && $this->shippingAddressId;
    }

    /**
     * @return Commerce_OrderStatusModel|null
     */
    public function getOrderStatus()
    {
        return craft()->commerce_orderStatuses->getOrderStatusById($this->orderStatusId);
    }

    /**
     * @return array
     */
    protected function defineAttributes()
    {
        return array_merge(parent::defineAttributes(), [
            'id' => AttributeType::Number,
            'number' => AttributeType::String,
            'couponCode' => AttributeType::String,
            'itemTotal' => [
                AttributeType::Number,
                'decimals' => 4,
                'default' => 0
            ],
            'baseDiscount' => [
                AttributeType::Number,
                'decimals' => 4,
                'default' => 0
            ],
            'baseShippingCost' => [
                AttributeType::Number,
                'decimals' => 4,
                'default' => 0
            ],
            'baseTax' => [
                AttributeType::Number,
                'decimals' => 4,
                'default' => 0
            ],
            'baseTaxIncluded' => [
                AttributeType::Number,
                'decimals' => 4,
                'default' => 0
            ],
            'totalPrice' => [
                AttributeType::Number,
                'decimals' => 4,
                'default' => 0
            ],
            'totalPaid' => [
                AttributeType::Number,
                'decimals' => 4,
                'default' => 0
            ],
            'isCompleted' => AttributeType::Bool,
            'dateOrdered' => AttributeType::DateTime,
            'datePaid' => AttributeType::DateTime,
            'currency' => AttributeType::String,
            'paymentCurrency' => AttributeType::String,
            'lastIp' => AttributeType::String,
            'orderLocale' => AttributeType::String,
            'message' => AttributeType::String,
            'returnUrl' => AttributeType::String,
            'cancelUrl' => AttributeType::String,
            'orderStatusId' => AttributeType::Number,
            'billingAddressId' => AttributeType::Number,
            'shippingAddressId' => AttributeType::Number,
            'shippingMethod' => AttributeType::String,
            'paymentMethodId' => AttributeType::Number,
            'customerId' => AttributeType::Number
        ]);
    }
}
