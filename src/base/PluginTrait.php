<?php
namespace craft\commerce\base;

use craft\commerce\Plugin;

/**
 * PluginTrait
 *
 */
trait PluginTrait
{
    /**
     * Returns the address service
     *
     * @return \craft\commerce\services\Addresses The address service
     */
    public function getAddresses()
    {
        return $this->get('addresses');
    }

    /**
     * Returns the cart service
     *
     * @return \craft\commerce\services\Cart The cart service
     */
    public function getCart()
    {
        return $this->get('cart');
    }

    /**
     * Returns the countries service
     *
     * @return \craft\commerce\services\Countries The countries service
     */
    public function getCountries()
    {
        return $this->get('countries');
    }

    /**
     * Returns the currencies service
     *
     * @return \craft\commerce\services\Currencies The currencies service
     */
    public function getCurrencies()
    {
        return $this->get('currencies');
    }

    /**
     * Returns the customers service
     *
     * @return \craft\commerce\services\Customers The customers service
     */
    public function getCustomers()
    {
        return $this->get('customers');
    }

    /**
     * Returns the discounts service
     *
     * @return \craft\commerce\services\Discounts The discounts service
     */
    public function getDiscounts()
    {
        return $this->get('discounts');
    }

    /**
     * Returns the emails service
     *
     * @return \craft\commerce\services\Emails The emails service
     */
    public function getEmails()
    {
        return $this->get('emails');
    }

    /**
     * Returns the gateways service
     *
     * @return \craft\commerce\services\Gateways The gateways service
     */
    public function getGateways()
    {
        return $this->get('gateways');
    }

    /**
     * Returns the lineItems service
     *
     * @return \craft\commerce\services\LineItems The lineItems service
     */
    public function getLineItems()
    {
        return $this->get('lineItems');
    }

    /**
     * Returns the orderAdjustments service
     *
     * @return \craft\commerce\services\OrderAdjustments The orderAdjustments service
     */
    public function getOrderAdjustments()
    {
        return $this->get('orderAdjustments');
    }

    /**
     * Returns the orderHistories service
     *
     * @return \craft\commerce\services\OrderHistories The orderHistories service
     */
    public function getOrderHistories()
    {
        return $this->get('orderHistories');
    }

    /**
     * Returns the orders service
     *
     * @return \craft\commerce\services\Orders The orders service
     */
    public function getOrders()
    {
        return $this->get('orders');
    }

    /**
     * Returns the orderSettings service
     *
     * @return \craft\commerce\services\OrderSettings The orderSettings service
     */
    public function getOrderSettings()
    {
        return $this->get('orderSettings');
    }

    /**
     * Returns the orderStatuses service
     *
     * @return \craft\commerce\services\OrderStatuses The orderStatuses service
     */
    public function getOrderStatuses()
    {
        return $this->get('orderStatuses');
    }

    /**
     * Returns the paymentMethods service
     *
     * @return \craft\commerce\services\PaymentMethods The paymentMethods service
     */
    public function getPaymentMethods()
    {
        return $this->get('paymentMethods');
    }

    /**
     * Returns the paymentCurrencies service
     *
     * @return \craft\commerce\services\PaymentCurrencies The paymentCurrencies service
     */
    public function getPaymentCurrencies()
    {
        return $this->get('paymentCurrencies');
    }

    /**
     * Returns the payments service
     *
     * @return \craft\commerce\services\Payments The payments service
     */
    public function getPayments()
    {
        return $this->get('payments');
    }

    /**
     * Returns the products service
     *
     * @return \craft\commerce\services\Products The products service
     */
    public function getProducts()
    {
        return $this->get('products');
    }

    /**
     * Returns the productTypes service
     *
     * @return \craft\commerce\services\ProductTypes The productTypes service
     */
    public function getProductTypes()
    {
        return $this->get('productTypes');
    }

    /**
     * Returns the purchasables service
     *
     * @return \craft\commerce\services\Purchasables The purchasables service
     */
    public function getPurchasables()
    {
        return $this->get('purchasables');
    }

    /**
     * Returns the sales service
     *
     * @return \craft\commerce\services\Sales The sales service
     */
    public function getSales()
    {
        return $this->get('sales');
    }

    /**
     * Returns the seed service
     *
     * @return \craft\commerce\services\Seed The seed service
     */
    public function getSeed()
    {
        return $this->get('seed');
    }

    /**
     * Returns the settings service
     *
     * @return \craft\commerce\services\Settings The settings service
     */
    public function getSettings()
    {
        return $this->get('settings');
    }

    /**
     * Returns the shippingMethods service
     *
     * @return \craft\commerce\services\ShippingMethods The shippingMethods service
     */
    public function getShippingMethods()
    {
        return $this->get('shippingMethods');
    }

    /**
     * Returns the shippingRules service
     *
     * @return \craft\commerce\services\ShippingRules The shippingRules service
     */
    public function getShippingRules()
    {
        return $this->get('shippingRules');
    }

    /**
     * Returns the shippingCategories service
     *
     * @return \craft\commerce\services\ShippingCategories The shippingCategories service
     */
    public function getShippingCategories()
    {
        return $this->get('shippingCategories');
    }

    /**
     * Returns the shippingZones service
     *
     * @return \craft\commerce\services\ShippingZones The shippingZones service
     */
    public function getShippingZones()
    {
        return $this->get('shippingZones');
    }

    /**
     * Returns the states service
     *
     * @return \craft\commerce\services\States The states service
     */
    public function getStates()
    {
        return $this->get('states');
    }

    /**
     * Returns the taxCategories service
     *
     * @return \craft\commerce\services\TaxCategories The taxCategories service
     */
    public function getTaxCategories()
    {
        return $this->get('taxCategories');
    }

    /**
     * Returns the taxRates service
     *
     * @return \craft\commerce\services\TaxRates The taxRates service
     */
    public function getTaxRates()
    {
        return $this->get('taxRates');
    }

    /**
     * Returns the taxZones service
     *
     * @return \craft\commerce\services\TaxZones The taxZones service
     */
    public function getTaxZones()
    {
        return $this->get('taxZones');
    }

    /**
     * Returns the transactions service
     *
     * @return \craft\commerce\services\Transactions The transactions service
     */
    public function getTransactions()
    {
        return $this->get('transactions');
    }

    /**
     * Returns the variants service
     *
     * @return \craft\commerce\services\Variants The variants service
     */
    public function getVariants()
    {
        return $this->get('variants');
    }

    /**
     * Initialize the plugin.
     */
    private function _init()
    {
        $this->_setPluginComponents();
        // Fire an 'afterInit' event
        $this->trigger(Plugin::EVENT_AFTER_INIT);
    }

    /**
     * Set the components of the commerce plugin
     */
    private function _setPluginComponents()
    {
        $this->setComponents([
            'addresses' => \craft\commerce\services\Addresses::class,
            'cart' => \craft\commerce\services\Cart::class,
            'countries' => \craft\commerce\services\Countries::class,
            'currencies' => \craft\commerce\services\Currencies::class,
            'customers' => \craft\commerce\services\Customers::class,
            'discounts' => \craft\commerce\services\Discounts::class,
            'emails' => \craft\commerce\services\Emails::class,
            'gateways' => \craft\commerce\services\Gateways::class,
            'lineItems' => \craft\commerce\services\LineItems::class,
            'orderAdjustments' => \craft\commerce\services\OrderAdjustments::class,
            'orderHistories' => \craft\commerce\services\OrderHistories::class,
            'orders' => \craft\commerce\services\Orders::class,
            'orderSettings' => \craft\commerce\services\OrderSettings::class,
            'orderStatuses' => \craft\commerce\services\OrderStatuses::class,
            'paymentMethods' => \craft\commerce\services\PaymentMethods::class,
            'paymentCurrencies' => \craft\commerce\services\PaymentCurrencies::class,
            'payments' => \craft\commerce\services\Payments::class,
            'products' => \craft\commerce\services\Products::class,
            'productTypes' => \craft\commerce\services\ProductTypes::class,
            'purchasables' => \craft\commerce\services\Purchasables::class,
            'sales' => \craft\commerce\services\Sales::class,
            'seed' => \craft\commerce\services\Seed::class,
            'settings' => \craft\commerce\services\Settings::class,
            'shippingMethods' => \craft\commerce\services\ShippingMethods::class,
            'shippingRules' => \craft\commerce\services\ShippingRules::class,
            'shippingCategories' => \craft\commerce\services\ShippingCategories::class,
            'shippingZones' => \craft\commerce\services\ShippingZones::class,
            'states' => \craft\commerce\services\States::class,
            'taxCategories' => \craft\commerce\services\TaxCategories::class,
            'taxRates' => \craft\commerce\services\TaxRates::class,
            'taxZones' => \craft\commerce\services\TaxZones::class,
            'transactions' => \craft\commerce\services\Transactions::class,
            'variants' => \craft\commerce\services\Variants::class
        ]);
    }
}