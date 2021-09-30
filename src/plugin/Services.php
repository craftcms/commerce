<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\plugin;

use craft\commerce\services\AddressBook;
use craft\commerce\services\Addresses;
use craft\commerce\services\Carts;
use craft\commerce\services\Countries;
use craft\commerce\services\Currencies;
use craft\commerce\services\Customers;
use craft\commerce\services\Discounts;
use craft\commerce\services\Emails;
use craft\commerce\services\Formulas;
use craft\commerce\services\Gateways;
use craft\commerce\services\LineItems;
use craft\commerce\services\LineItemStatuses;
use craft\commerce\services\OrderAdjustments;
use craft\commerce\services\OrderHistories;
use craft\commerce\services\OrderNotices;
use craft\commerce\services\Orders;
use craft\commerce\services\OrderStatuses;
use craft\commerce\services\PaymentCurrencies;
use craft\commerce\services\Payments;
use craft\commerce\services\PaymentSources;
use craft\commerce\services\Pdfs;
use craft\commerce\services\Plans;
use craft\commerce\services\Products;
use craft\commerce\services\ProductTypes;
use craft\commerce\services\Purchasables;
use craft\commerce\services\Sales;
use craft\commerce\services\ShippingCategories;
use craft\commerce\services\ShippingMethods;
use craft\commerce\services\ShippingRuleCategories;
use craft\commerce\services\ShippingRules;
use craft\commerce\services\ShippingZones;
use craft\commerce\services\States;
use craft\commerce\services\Subscriptions;
use craft\commerce\services\TaxCategories;
use craft\commerce\services\Taxes;
use craft\commerce\services\TaxRates;
use craft\commerce\services\TaxZones;
use craft\commerce\services\Transactions;
use craft\commerce\services\Users;
use craft\commerce\services\Variants;
use craft\commerce\services\Webhooks;
use yii\base\InvalidConfigException;

/**
 * Trait Services
 *
 * @property Addresses $addresses the address service
 * @property Carts $cart the cart service
 * @property Countries $countries the countries service
 * @property Currencies $currencies the currencies service
 * @property Customers $customers the customers service
 * @property Discounts $discounts the discounts service
 * @property Emails $emails the emails service
 * @property Gateways $gateways the gateways service
 * @property LineItems $lineItems the line items service
 * @property OrderAdjustments $orderAdjustments the order adjustments service
 * @property OrderHistories $orderHistories the order histories service
 * @property Orders $orders the orders service
 * @property OrderStatuses $orderNotices the order notices service
 * @property OrderStatuses $orderStatuses the orderStatuses service
 * @property PaymentCurrencies $paymentCurrencies the paymentCurrencies service
 * @property Payments $payments the payments service
 * @property PaymentSources $paymentSources the payment sources service
 * @property Pdfs $pdf the pdf service
 * @property Plans $plans the plans service
 * @property Products $products the products service
 * @property ProductTypes $productTypes the product types service
 * @property Purchasables $purchasables the purchasables service
 * @property Sales $sales the sales service
 * @property ShippingMethods $shippingMethods the shipping methods service
 * @property ShippingRules $shippingRules the shipping rules service
 * @property ShippingRuleCategories $shippingRuleCategories the shipping rule categories service
 * @property ShippingCategories $shippingCategories the shipping categories service
 * @property ShippingZones $shippingZones the shipping zones service
 * @property States $states the states service
 * @property Subscriptions $subscriptions the subscriptions service
 * @property TaxCategories $taxCategories the taxCategories service
 * @property TaxRates $taxRates the taxRates service
 * @property TaxZones $taxZones the taxZones service
 * @property Transactions $transactions the transactions service
 * @property Variants $variants the variants service
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
trait Services
{
    /**
     * Returns the address book service
     *
     * @return AddressBook The address book service
     * @throws InvalidConfigException
     * @since 4.0
     */
    public function getAddressBook(): AddressBook
    {
        return $this->get('addressBook');
    }

    /**
     * Returns the address service
     *
     * @return Addresses The address service
     * @throws InvalidConfigException
     */
    public function getAddresses(): Addresses
    {
        return $this->get('addresses');
    }

    /**
     * Returns the cart service
     *
     * @return Carts The cart service
     * @throws InvalidConfigException
     */
    public function getCarts(): Carts
    {
        return $this->get('carts');
    }

    /**
     * Returns the countries service
     *
     * @return Countries The countries service
     * @throws InvalidConfigException
     */
    public function getCountries(): Countries
    {
        return $this->get('countries');
    }

    /**
     * Returns the currencies service
     *
     * @return Currencies The currencies service
     * @throws InvalidConfigException
     */
    public function getCurrencies(): Currencies
    {
        return $this->get('currencies');
    }

    /**
     * Returns the customers service
     *
     * @return Customers The customers service
     * @throws InvalidConfigException
     */
    public function getCustomers(): Customers
    {
        return $this->get('customers');
    }

    /**
     * Returns the discounts service
     *
     * @return Discounts The discounts service
     * @throws InvalidConfigException
     */
    public function getDiscounts(): Discounts
    {
        return $this->get('discounts');
    }

    /**
     * Returns the emails service
     *
     * @return Emails The emails service
     * @throws InvalidConfigException
     */
    public function getEmails(): Emails
    {
        return $this->get('emails');
    }

    /**
     * Returns the formulas service
     *
     * @return Formulas the formulas service
     * @throws InvalidConfigException
     * @since 2.2
     */
    public function getFormulas(): Formulas
    {
        return $this->get('formulas');
    }

    /**
     * Returns the gateways service
     *
     * @return Gateways The gateways service
     * @throws InvalidConfigException
     */
    public function getGateways(): Gateways
    {
        return $this->get('gateways');
    }

    /**
     * Returns the lineItems service
     *
     * @return LineItems The lineItems service
     * @throws InvalidConfigException
     */
    public function getLineItems(): LineItems
    {
        return $this->get('lineItems');
    }

    /**
     * Returns the lineItems statuses service
     *
     * @return LineItemStatuses The lineItems service
     * @throws InvalidConfigException
     */
    public function getLineItemStatuses(): LineItemStatuses
    {
        return $this->get('lineItemStatuses');
    }

    /**
     * Returns the orderAdjustments service
     *
     * @return OrderAdjustments The orderAdjustments service
     * @throws InvalidConfigException
     */
    public function getOrderAdjustments(): OrderAdjustments
    {
        return $this->get('orderAdjustments');
    }

    /**
     * Returns the orderHistories service
     *
     * @return OrderHistories The orderHistories service
     * @throws InvalidConfigException
     */
    public function getOrderHistories(): OrderHistories
    {
        return $this->get('orderHistories');
    }

    /**
     * Returns the orders service
     *
     * @return Orders The orders service
     * @throws InvalidConfigException
     */
    public function getOrders(): Orders
    {
        return $this->get('orders');
    }

    /**
     * Returns the OrderNotices service
     *
     * @return OrderNotices The OrderNotices service
     * @throws InvalidConfigException
     */
    public function getOrderNotices(): OrderNotices
    {
        return $this->get('orderNotices');
    }

    /**
     * Returns the OrderStatuses service
     *
     * @return OrderStatuses The OrderStatuses service
     * @throws InvalidConfigException
     */
    public function getOrderStatuses(): OrderStatuses
    {
        return $this->get('orderStatuses');
    }

    /**
     * Returns the paymentCurrencies service
     *
     * @return PaymentCurrencies The paymentCurrencies service
     * @throws InvalidConfigException
     */
    public function getPaymentCurrencies(): PaymentCurrencies
    {
        return $this->get('paymentCurrencies');
    }

    /**
     * Returns the payments service
     *
     * @return Payments The payments service
     * @throws InvalidConfigException
     */
    public function getPayments(): Payments
    {
        return $this->get('payments');
    }

    /**
     * Returns the payment sources service
     *
     * @return PaymentSources The payment sources service
     * @throws InvalidConfigException
     */
    public function getPaymentSources(): PaymentSources
    {
        return $this->get('paymentSources');
    }

    /**
     * Returns the PDFs service
     *
     * @return Pdfs The PDFs service
     * @throws InvalidConfigException
     */
    public function getPdfs(): Pdfs
    {
        return $this->get('pdfs');
    }

    /**
     * Returns the payment sources service
     *
     * @return Plans The subscription plans service
     * @throws InvalidConfigException
     */
    public function getPlans(): Plans
    {
        return $this->get('plans');
    }

    /**
     * Returns the products service
     *
     * @return Products The products service
     * @throws InvalidConfigException
     */
    public function getProducts(): Products
    {
        return $this->get('products');
    }

    /**
     * Returns the productTypes service
     *
     * @return ProductTypes The productTypes service
     * @throws InvalidConfigException
     */
    public function getProductTypes(): ProductTypes
    {
        return $this->get('productTypes');
    }

    /**
     * Returns the purchasables service
     *
     * @return Purchasables The purchasables service
     * @throws InvalidConfigException
     */
    public function getPurchasables(): Purchasables
    {
        return $this->get('purchasables');
    }

    /**
     * Returns the sales service
     *
     * @return Sales The sales service
     * @throws InvalidConfigException
     */
    public function getSales(): Sales
    {
        return $this->get('sales');
    }

    /**
     * Returns the shippingMethods service
     *
     * @return ShippingMethods The shippingMethods service
     * @throws InvalidConfigException
     */
    public function getShippingMethods(): ShippingMethods
    {
        return $this->get('shippingMethods');
    }

    /**
     * Returns the shippingRules service
     *
     * @return ShippingRules The shippingRules service
     * @throws InvalidConfigException
     */
    public function getShippingRules(): ShippingRules
    {
        return $this->get('shippingRules');
    }

    /**
     * Returns the shippingRules service
     *
     * @return ShippingRuleCategories The shippingRuleCategories service
     * @throws InvalidConfigException
     */
    public function getShippingRuleCategories(): ShippingRuleCategories
    {
        return $this->get('shippingRuleCategories');
    }

    /**
     * Returns the shippingCategories service
     *
     * @return ShippingCategories The shippingCategories service
     * @throws InvalidConfigException
     */
    public function getShippingCategories(): ShippingCategories
    {
        return $this->get('shippingCategories');
    }

    /**
     * Returns the shippingZones service
     *
     * @return ShippingZones The shippingZones service
     * @throws InvalidConfigException
     */
    public function getShippingZones(): ShippingZones
    {
        return $this->get('shippingZones');
    }

    /**
     * Returns the states service
     *
     * @return States The states service
     * @throws InvalidConfigException
     */
    public function getStates(): States
    {
        return $this->get('states');
    }

    /**
     * Returns the subscriptions service
     *
     * @return Subscriptions The subscriptions service
     * @throws InvalidConfigException
     */
    public function getSubscriptions(): Subscriptions
    {
        return $this->get('subscriptions');
    }

    /**
     * Returns the taxes service
     *
     * @return Taxes The taxes service
     * @throws InvalidConfigException
     */
    public function getTaxes(): Taxes
    {
        return $this->get('taxes');
    }

    /**
     * Returns the taxCategories service
     *
     * @return TaxCategories The taxCategories service
     * @throws InvalidConfigException
     */
    public function getTaxCategories(): TaxCategories
    {
        return $this->get('taxCategories');
    }

    /**
     * Returns the taxRates service
     *
     * @return TaxRates The taxRates service
     * @throws InvalidConfigException
     */
    public function getTaxRates(): TaxRates
    {
        return $this->get('taxRates');
    }

    /**
     * Returns the taxZones service
     *
     * @return TaxZones The taxZones service
     * @throws InvalidConfigException
     */
    public function getTaxZones(): TaxZones
    {
        return $this->get('taxZones');
    }

    /**
     * Returns the transactions service
     *
     * @return Transactions The transactions service
     * @throws InvalidConfigException
     */
    public function getTransactions(): Transactions
    {
        return $this->get('transactions');
    }

    /**
     * Returns the variants service
     *
     * @return Variants The variants service
     * @throws InvalidConfigException
     */
    public function getVariants(): Variants
    {
        return $this->get('variants');
    }

    /**
     * Returns the webhooks service
     *
     * @return Webhooks The variants service
     * @throws InvalidConfigException
     * @since 3.1.9
     */
    public function getWebhooks(): Webhooks
    {
        return $this->get('webhooks');
    }


    /**
     * Sets the components of the commerce plugin
     */
    private function _setPluginComponents(): void
    {
        $this->setComponents([
            'addressBook' => [
                'class' => AddressBook::class,
            ],
            'addresses' => [
                'class' => Addresses::class,
            ],
            'carts' => [
                'class' => Carts::class,
            ],
            'countries' => [
                'class' => Countries::class,
            ],
            'currencies' => [
                'class' => Currencies::class,
            ],
            'customers' => [
                'class' => Customers::class,
            ],
            'discounts' => [
                'class' => Discounts::class,
            ],
            'emails' => [
                'class' => Emails::class,
            ],
            'formulas' => [
                'class' => Formulas::class,
            ],
            'gateways' => [
                'class' => Gateways::class,
            ],
            'lineItems' => [
                'class' => LineItems::class,
            ],
            'lineItemStatuses' => [
                'class' => LineItemStatuses::class,
            ],
            'orderAdjustments' => [
                'class' => OrderAdjustments::class,
            ],
            'orderHistories' => [
                'class' => OrderHistories::class,
            ],
            'orders' => [
                'class' => Orders::class,
            ],
            'orderNotices' => [
                'class' => OrderNotices::class,
            ],
            'orderStatuses' => [
                'class' => OrderStatuses::class,
            ],
            'paymentMethods' => [
                'class' => Gateways::class,
            ],
            'paymentCurrencies' => [
                'class' => PaymentCurrencies::class,
            ],
            'payments' => [
                'class' => Payments::class,
            ],
            'paymentSources' => [
                'class' => PaymentSources::class,
            ],
            'pdfs' => [
                'class' => Pdfs::class,
            ],
            'plans' => [
                'class' => Plans::class,
            ],
            'products' => [
                'class' => Products::class,
            ],
            'productTypes' => [
                'class' => ProductTypes::class,
            ],
            'purchasables' => [
                'class' => Purchasables::class,
            ],
            'sales' => [
                'class' => Sales::class,
            ],
            'shippingMethods' => [
                'class' => ShippingMethods::class,
            ],
            'shippingRules' => [
                'class' => ShippingRules::class,
            ],
            'shippingRuleCategories' => [
                'class' => ShippingRuleCategories::class,
            ],
            'shippingCategories' => [
                'class' => ShippingCategories::class,
            ],
            'shippingZones' => [
                'class' => ShippingZones::class,
            ],
            'states' => [
                'class' => States::class,
            ],
            'subscriptions' => [
                'class' => Subscriptions::class,
            ],
            'taxCategories' => [
                'class' => TaxCategories::class,
            ],
            'taxes' => [
                'class' => Taxes::class,
            ],
            'taxRates' => [
                'class' => TaxRates::class,
            ],
            'taxZones' => [
                'class' => TaxZones::class,
            ],
            'transactions' => [
                'class' => Transactions::class,
            ],
            'variants' => [
                'class' => Variants::class,
            ],
            'webhooks' => [
                'class' => Webhooks::class,
            ],
        ]);
    }
}
