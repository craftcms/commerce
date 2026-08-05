<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Plugin\Concerns;

use craft\commerce\services\Carts;
use craft\commerce\services\CatalogPricing;
use craft\commerce\services\CatalogPricingRules;
use craft\commerce\services\Coupons;
use craft\commerce\services\Currencies;
use craft\commerce\services\Customers;
use craft\commerce\services\Discounts;
use craft\commerce\services\Emails;
use craft\commerce\services\Formulas;
use craft\commerce\services\Gateways;
use craft\commerce\services\Inventory;
use craft\commerce\services\InventoryLocations;
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
use craft\commerce\services\Store;
use craft\commerce\services\Stores;
use craft\commerce\services\StoreSettings;
use craft\commerce\services\Subscriptions;
use craft\commerce\services\TaxCategories;
use craft\commerce\services\Taxes;
use craft\commerce\services\TaxRates;
use craft\commerce\services\TaxZones;
use craft\commerce\services\Transactions;
use craft\commerce\services\Transfers;
use craft\commerce\services\Variants;
use craft\commerce\services\Vat;
use craft\commerce\services\Webhooks;
use yii\base\InvalidConfigException;

/**
 * Replaces the Yii2 `yii\di\ServiceLocator` component locator that `Plugin::getInstance()->getFoo()`
 * relied on when Commerce extended `craft\base\Plugin` (a `yii\base\Module`). Every one of these 46
 * getters is still called throughout src-yii2/ exactly as before; only the underlying lazy-instantiate-
 * and-cache mechanism changed.
 */
trait HasServices
{
    /** @var array<string, object> */
    private array $serviceInstances = [];

    /** @var array<string, class-string> */
    private static array $serviceMap = [
        'carts' => Carts::class,
        'catalogPricing' => CatalogPricing::class,
        'catalogPricingRules' => CatalogPricingRules::class,
        'coupons' => Coupons::class,
        'currencies' => Currencies::class,
        'customers' => Customers::class,
        'discounts' => Discounts::class,
        'emails' => Emails::class,
        'formulas' => Formulas::class,
        'gateways' => Gateways::class,
        'inventory' => Inventory::class,
        'inventoryLocations' => InventoryLocations::class,
        'lineItems' => LineItems::class,
        'lineItemStatuses' => LineItemStatuses::class,
        'orderAdjustments' => OrderAdjustments::class,
        'orderHistories' => OrderHistories::class,
        'orderNotices' => OrderNotices::class,
        'orders' => Orders::class,
        'orderStatuses' => OrderStatuses::class,
        'paymentCurrencies' => PaymentCurrencies::class,
        // Legacy alias kept for any third-party code still doing get('paymentMethods') directly.
        'paymentMethods' => Gateways::class,
        'payments' => Payments::class,
        'paymentSources' => PaymentSources::class,
        'pdfs' => Pdfs::class,
        'plans' => Plans::class,
        'products' => Products::class,
        'productTypes' => ProductTypes::class,
        'purchasables' => Purchasables::class,
        'sales' => Sales::class,
        'shippingCategories' => ShippingCategories::class,
        'shippingMethods' => ShippingMethods::class,
        'shippingRuleCategories' => ShippingRuleCategories::class,
        'shippingRules' => ShippingRules::class,
        'shippingZones' => ShippingZones::class,
        'store' => Store::class,
        'storeSettings' => StoreSettings::class,
        'stores' => Stores::class,
        'subscriptions' => Subscriptions::class,
        'taxCategories' => TaxCategories::class,
        'taxes' => Taxes::class,
        'taxRates' => TaxRates::class,
        'taxZones' => TaxZones::class,
        'transactions' => Transactions::class,
        'transfers' => Transfers::class,
        'variants' => Variants::class,
        'vat' => Vat::class,
        'webhooks' => Webhooks::class,
    ];

    /**
     * Returns a legacy service component by ID, matching the `yii\di\ServiceLocator::get()` API
     * that third-party code may still call directly.
     *
     * @throws InvalidConfigException if no service is registered under that ID
     */
    public function get(string $id): object
    {
        if (!isset(self::$serviceMap[$id])) {
            throw new InvalidConfigException("Unknown component ID: $id");
        }

        $class = self::$serviceMap[$id];

        return $this->serviceInstances[$id] ??= new $class();
    }

    public function getCarts(): Carts
    {
        return $this->get('carts');
    }

    public function getCatalogPricing(): CatalogPricing
    {
        return $this->get('catalogPricing');
    }

    public function getCatalogPricingRules(): CatalogPricingRules
    {
        return $this->get('catalogPricingRules');
    }

    public function getCoupons(): Coupons
    {
        return $this->get('coupons');
    }

    public function getCurrencies(): Currencies
    {
        return $this->get('currencies');
    }

    public function getCustomers(): Customers
    {
        return $this->get('customers');
    }

    public function getDiscounts(): Discounts
    {
        return $this->get('discounts');
    }

    public function getEmails(): Emails
    {
        return $this->get('emails');
    }

    public function getFormulas(): Formulas
    {
        return $this->get('formulas');
    }

    public function getGateways(): Gateways
    {
        return $this->get('gateways');
    }

    public function getInventory(): Inventory
    {
        return $this->get('inventory');
    }

    public function getInventoryLocations(): InventoryLocations
    {
        return $this->get('inventoryLocations');
    }

    public function getLineItems(): LineItems
    {
        return $this->get('lineItems');
    }

    public function getLineItemStatuses(): LineItemStatuses
    {
        return $this->get('lineItemStatuses');
    }

    public function getOrderAdjustments(): OrderAdjustments
    {
        return $this->get('orderAdjustments');
    }

    public function getOrderHistories(): OrderHistories
    {
        return $this->get('orderHistories');
    }

    public function getOrderNotices(): OrderNotices
    {
        return $this->get('orderNotices');
    }

    public function getOrders(): Orders
    {
        return $this->get('orders');
    }

    public function getOrderStatuses(): OrderStatuses
    {
        return $this->get('orderStatuses');
    }

    public function getPaymentCurrencies(): PaymentCurrencies
    {
        return $this->get('paymentCurrencies');
    }

    public function getPayments(): Payments
    {
        return $this->get('payments');
    }

    public function getPaymentSources(): PaymentSources
    {
        return $this->get('paymentSources');
    }

    public function getPdfs(): Pdfs
    {
        return $this->get('pdfs');
    }

    public function getPlans(): Plans
    {
        return $this->get('plans');
    }

    public function getProducts(): Products
    {
        return $this->get('products');
    }

    public function getProductTypes(): ProductTypes
    {
        return $this->get('productTypes');
    }

    public function getPurchasables(): Purchasables
    {
        return $this->get('purchasables');
    }

    public function getSales(): Sales
    {
        return $this->get('sales');
    }

    public function getShippingCategories(): ShippingCategories
    {
        return $this->get('shippingCategories');
    }

    public function getShippingMethods(): ShippingMethods
    {
        return $this->get('shippingMethods');
    }

    public function getShippingRuleCategories(): ShippingRuleCategories
    {
        return $this->get('shippingRuleCategories');
    }

    public function getShippingRules(): ShippingRules
    {
        return $this->get('shippingRules');
    }

    public function getShippingZones(): ShippingZones
    {
        return $this->get('shippingZones');
    }

    public function getStore(): Store
    {
        return $this->get('store');
    }

    public function getStoreSettings(): StoreSettings
    {
        return $this->get('storeSettings');
    }

    public function getStores(): Stores
    {
        return $this->get('stores');
    }

    public function getSubscriptions(): Subscriptions
    {
        return $this->get('subscriptions');
    }

    public function getTaxCategories(): TaxCategories
    {
        return $this->get('taxCategories');
    }

    public function getTaxes(): Taxes
    {
        return $this->get('taxes');
    }

    public function getTaxRates(): TaxRates
    {
        return $this->get('taxRates');
    }

    public function getTaxZones(): TaxZones
    {
        return $this->get('taxZones');
    }

    public function getTransactions(): Transactions
    {
        return $this->get('transactions');
    }

    public function getTransfers(): Transfers
    {
        return $this->get('transfers');
    }

    public function getVariants(): Variants
    {
        return $this->get('variants');
    }

    public function getVat(): Vat
    {
        return $this->get('vat');
    }

    public function getWebhooks(): Webhooks
    {
        return $this->get('webhooks');
    }
}
