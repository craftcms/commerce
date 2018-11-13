<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\plugin;

use craft\commerce\services\Addresses;
use craft\commerce\services\Carts;
use craft\commerce\services\Countries;
use craft\commerce\services\Currencies;
use craft\commerce\services\Customers;
use craft\commerce\services\Discounts;
use craft\commerce\services\Emails;
use craft\commerce\services\Gateways;
use craft\commerce\services\LineItems;
use craft\commerce\services\OrderAdjustments;
use craft\commerce\services\OrderHistories;
use craft\commerce\services\Orders;
use craft\commerce\services\OrderSettings;
use craft\commerce\services\OrderStatuses;
use craft\commerce\services\PaymentCurrencies;
use craft\commerce\services\Payments;
use craft\commerce\services\PaymentSources;
use craft\commerce\services\Pdf;
use craft\commerce\services\Plans;
use craft\commerce\services\Products;
use craft\commerce\services\ProductTypes;
use craft\commerce\services\Purchasables;
use craft\commerce\services\Reports;
use craft\commerce\services\Sales;
use craft\commerce\services\ShippingCategories;
use craft\commerce\services\ShippingMethods;
use craft\commerce\services\ShippingRuleCategories;
use craft\commerce\services\ShippingRules;
use craft\commerce\services\ShippingZones;
use craft\commerce\services\States;
use craft\commerce\services\Subscriptions;
use craft\commerce\services\TaxCategories;
use craft\commerce\services\TaxRates;
use craft\commerce\services\TaxZones;
use craft\commerce\services\Transactions;
use craft\commerce\services\Variants;

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
 * @property LineItems $lineItems the lineItems service
 * @property OrderAdjustments $orderAdjustments the orderAdjustments service
 * @property OrderHistories $orderHistories the orderHistories service
 * @property Orders $orders the orders service
 * @property OrderSettings $orderSettings the orderSettings service
 * @property OrderStatuses $orderStatuses the orderStatuses service
 * @property PaymentCurrencies $paymentCurrencies the paymentCurrencies service
 * @property Payments $payments the payments service
 * @property PaymentSources $paymentSources the payment sources service
 * @property Pdf $pdf the pdf service
 * @property Plans $plans the plans service
 * @property Products $products the products service
 * @property ProductTypes $productTypes the productTypes service
 * @property Purchasables $purchasables the purchasables service
 * @property Sales $sales the sales service
 * @property ShippingMethods $shippingMethods the shippingCategories service
 * @property ShippingRules $shippingRules the shippingRules service
 * @property ShippingRuleCategories $shippingRuleCategories the shippingRules service
 * @property ShippingCategories $shippingCategories the shippingCategories service
 * @property ShippingZones $shippingZones the shippingZones service
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
    // Public Methods
    // =========================================================================

    /**
     * Returns the address service
     *
     * @return Addresses The address service
     */
    public function getAddresses(): Addresses
    {
        return $this->get('addresses');
    }

    /**
     * Returns the cart service
     *
     * @return Carts The cart service
     */
    public function getCarts(): Carts
    {
        return $this->get('carts');
    }

    /**
     * Returns the countries service
     *
     * @return Countries The countries service
     */
    public function getCountries(): Countries
    {
        return $this->get('countries');
    }

    /**
     * Returns the currencies service
     *
     * @return Currencies The currencies service
     */
    public function getCurrencies(): Currencies
    {
        return $this->get('currencies');
    }

    /**
     * Returns the customers service
     *
     * @return Customers The customers service
     */
    public function getCustomers(): Customers
    {
        return $this->get('customers');
    }

    /**
     * Returns the discounts service
     *
     * @return Discounts The discounts service
     */
    public function getDiscounts(): Discounts
    {
        return $this->get('discounts');
    }

    /**
     * Returns the emails service
     *
     * @return Emails The emails service
     */
    public function getEmails(): Emails
    {
        return $this->get('emails');
    }

    /**
     * Returns the gateways service
     *
     * @return Gateways The gateways service
     */
    public function getGateways(): Gateways
    {
        return $this->get('gateways');
    }

    /**
     * Returns the lineItems service
     *
     * @return LineItems The lineItems service
     */
    public function getLineItems(): LineItems
    {
        return $this->get('lineItems');
    }

    /**
     * Returns the orderAdjustments service
     *
     * @return OrderAdjustments The orderAdjustments service
     */
    public function getOrderAdjustments(): OrderAdjustments
    {
        return $this->get('orderAdjustments');
    }

    /**
     * Returns the orderHistories service
     *
     * @return OrderHistories The orderHistories service
     */
    public function getOrderHistories(): OrderHistories
    {
        return $this->get('orderHistories');
    }

    /**
     * Returns the orders service
     *
     * @return Orders The orders service
     */
    public function getOrders(): Orders
    {
        return $this->get('orders');
    }

    /**
     * Returns the orderSettings service
     *
     * @return OrderSettings The orderSettings service
     */
    public function getOrderSettings(): OrderSettings
    {
        return $this->get('orderSettings');
    }

    /**
     * Returns the orderStatuses service
     *
     * @return OrderStatuses The orderStatuses service
     */
    public function getOrderStatuses(): OrderStatuses
    {
        return $this->get('orderStatuses');
    }

    /**
     * Returns the paymentCurrencies service
     *
     * @return PaymentCurrencies The paymentCurrencies service
     */
    public function getPaymentCurrencies(): PaymentCurrencies
    {
        return $this->get('paymentCurrencies');
    }

    /**
     * Returns the payments service
     *
     * @return Payments The payments service
     */
    public function getPayments(): Payments
    {
        return $this->get('payments');
    }

    /**
     * Returns the payment sources service
     *
     * @return PaymentSources The payment sources service
     */
    public function getPaymentSources(): PaymentSources
    {
        return $this->get('paymentSources');
    }

    /**
     * Returns the PDF service
     *
     * @return Pdf The PDF service
     */
    public function getPdf(): Pdf
    {
        return $this->get('pdf');
    }

    /**
     * Returns the payment sources service
     *
     * @return Plans The subscription plans service
     */
    public function getPlans(): Plans
    {
        return $this->get('plans');
    }

    /**
     * Returns the products service
     *
     * @return Products The products service
     */
    public function getProducts(): Products
    {
        return $this->get('products');
    }

    /**
     * Returns the productTypes service
     *
     * @return ProductTypes The productTypes service
     */
    public function getProductTypes(): ProductTypes
    {
        return $this->get('productTypes');
    }

    /**
     * Returns the purchasables service
     *
     * @return Purchasables The purchasables service
     */
    public function getPurchasables(): Purchasables
    {
        return $this->get('purchasables');
    }

    /**
     * Returns the reporting service
     *
     * @return Reports The reports service
     */
    public function getReports(): Reports
    {
        return $this->get('reports');
    }

    /**
     * Returns the sales service
     *
     * @return Sales The sales service
     */
    public function getSales(): Sales
    {
        return $this->get('sales');
    }

    /**
     * Returns the shippingMethods service
     *
     * @return ShippingMethods The shippingMethods service
     */
    public function getShippingMethods(): ShippingMethods
    {
        return $this->get('shippingMethods');
    }

    /**
     * Returns the shippingRules service
     *
     * @return ShippingRules The shippingRules service
     */
    public function getShippingRules(): ShippingRules
    {
        return $this->get('shippingRules');
    }

    /**
     * Returns the shippingRules service
     *
     * @return ShippingRuleCategories The shippingRuleCategories service
     */
    public function getShippingRuleCategories(): ShippingRuleCategories
    {
        return $this->get('shippingRuleCategories');
    }

    /**
     * Returns the shippingCategories service
     *
     * @return ShippingCategories The shippingCategories service
     */
    public function getShippingCategories(): ShippingCategories
    {
        return $this->get('shippingCategories');
    }

    /**
     * Returns the shippingZones service
     *
     * @return ShippingZones The shippingZones service
     */
    public function getShippingZones(): ShippingZones
    {
        return $this->get('shippingZones');
    }

    /**
     * Returns the states service
     *
     * @return States The states service
     */
    public function getStates(): States
    {
        return $this->get('states');
    }

    /**
     * Returns the subscriptions service
     *
     * @return Subscriptions The subscriptions service
     */
    public function getSubscriptions(): Subscriptions
    {
        return $this->get('subscriptions');
    }

    /**
     * Returns the taxCategories service
     *
     * @return TaxCategories The taxCategories service
     */
    public function getTaxCategories(): TaxCategories
    {
        return $this->get('taxCategories');
    }

    /**
     * Returns the taxRates service
     *
     * @return TaxRates The taxRates service
     */
    public function getTaxRates(): TaxRates
    {
        return $this->get('taxRates');
    }

    /**
     * Returns the taxZones service
     *
     * @return TaxZones The taxZones service
     */
    public function getTaxZones(): TaxZones
    {
        return $this->get('taxZones');
    }

    /**
     * Returns the transactions service
     *
     * @return Transactions The transactions service
     */
    public function getTransactions(): Transactions
    {
        return $this->get('transactions');
    }

    /**
     * Returns the variants service
     *
     * @return Variants The variants service
     */
    public function getVariants(): Variants
    {
        return $this->get('variants');
    }

    // Private Methods
    // =========================================================================

    /**
     * Sets the components of the commerce plugin
     */
    private function _setPluginComponents()
    {
        $this->setComponents([
            'addresses' => Addresses::class,
            'carts' => Carts::class,
            'countries' => Countries::class,
            'currencies' => Currencies::class,
            'customers' => Customers::class,
            'discounts' => Discounts::class,
            'emails' => Emails::class,
            'gateways' => Gateways::class,
            'lineItems' => LineItems::class,
            'orderAdjustments' => OrderAdjustments::class,
            'orderHistories' => OrderHistories::class,
            'orders' => Orders::class,
            'orderSettings' => OrderSettings::class,
            'orderStatuses' => OrderStatuses::class,
            'paymentMethods' => Gateways::class,
            'paymentCurrencies' => PaymentCurrencies::class,
            'payments' => Payments::class,
            'paymentSources' => PaymentSources::class,
            'pdf' => Pdf::class,
            'plans' => Plans::class,
            'products' => Products::class,
            'productTypes' => ProductTypes::class,
            'purchasables' => Purchasables::class,
            'reports' => Reports::class,
            'sales' => Sales::class,
            'shippingMethods' => ShippingMethods::class,
            'shippingRules' => ShippingRules::class,
            'shippingRuleCategories' => ShippingRuleCategories::class,
            'shippingCategories' => ShippingCategories::class,
            'shippingZones' => ShippingZones::class,
            'states' => States::class,
            'subscriptions' => Subscriptions::class,
            'taxCategories' => TaxCategories::class,
            'taxRates' => TaxRates::class,
            'taxZones' => TaxZones::class,
            'transactions' => Transactions::class,
            'variants' => Variants::class
        ]);
    }
}
