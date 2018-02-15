<?php

namespace craft\commerce\variables;

use Craft;
use craft\commerce\base\Gateway;
use craft\commerce\elements\db\OrderQuery;
use craft\commerce\elements\db\ProductQuery;
use craft\commerce\elements\db\VariantQuery;
use craft\commerce\elements\Order;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\commerce\models\Country;
use craft\commerce\models\Currency;
use craft\commerce\models\Customer;
use craft\commerce\models\Discount;
use craft\commerce\models\OrderStatus;
use craft\commerce\models\PaymentCurrency;
use craft\commerce\models\PaymentSource;
use craft\commerce\models\ProductType;
use craft\commerce\models\Sale;
use craft\commerce\models\ShippingCategory;
use craft\commerce\models\ShippingMethod;
use craft\commerce\models\ShippingZone;
use craft\commerce\models\State;
use craft\commerce\models\TaxCategory;
use craft\commerce\models\TaxRate;
use craft\commerce\models\TaxZone;
use craft\commerce\Plugin as CommercePlugin;
use craft\commerce\services\Addresses;
use craft\commerce\services\ShippingCategories;
use craft\helpers\ArrayHelper;

/**
 * Variable class.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
class Commerce
{
    // Public Methods
    // =========================================================================

    /**
     * @return array|string[]
     */
    public function purchasableElementTypes(): array
    {
        return CommercePlugin::getInstance()->getPurchasables()->getAllPurchasableElementTypes();
    }

    /**
     * Get the Address service.
     *
     * @return Addresses
     */
    public function getAddresses(): Addresses
    {
        return CommercePlugin::getInstance()->getAddresses();
    }

    /**
     * Get all available shipping methods for the current cart.
     *
     * @return array
     */
    public function getAvailableShippingMethods(): array
    {
        $cart = CommercePlugin::getInstance()->getCart()->getCart();

        return CommercePlugin::getInstance()->getShippingMethods()->getOrderedAvailableShippingMethods($cart);
    }

    /**
     * Get the current Cart.
     *
     * @return Order
     */
    public function getCart(): Order
    {
        return CommercePlugin::getInstance()->getCart()->getCart();
    }

    /**
     * Return all countries.
     *
     * @return Country[]
     */
    public function getCountries(): array
    {
        return ArrayHelper::toArray(CommercePlugin::getInstance()->getCountries()->getAllCountries());
    }

    /**
     * Return an array of country names, indexed by ID.
     *
     * @return array [id => name]
     */
    public function getCountriesList(): array
    {
        return CommercePlugin::getInstance()->getCountries()->getAllCountriesListData();
    }

    /**
     * Get all of the available currencies.
     *
     * @return Currency[]
     */
    public function getCurrencies(): array
    {
        return CommercePlugin::getInstance()->getCurrencies()->getAllCurrencies();
    }

    /**
     * Get the current customer.
     *
     * @return Customer
     */
    public function getCustomer(): Customer
    {
        return CommercePlugin::getInstance()->getCustomers()->getCustomer();
    }

    /**
     * Get a discount by its code.
     *
     * @param string $code the discount code
     *
     * @return Discount|null
     */
    public function getDiscountByCode($code)
    {
        return CommercePlugin::getInstance()->getDiscounts()->getDiscountByCode($code);
    }

    /**
     * Get all discounts.
     *
     * @return Discount[]
     */
    public function getDiscounts(): array
    {
        return CommercePlugin::getInstance()->getDiscounts()->getAllDiscounts();
    }

    /**
     * Returns all gateways enabled for front-end use.
     *
     * @param bool $asList Whether to return an array of gateway names indexed by ID. Defaults to `false`.
     *
     * @return array|Gateway[]
     */
    public function getGateways($asList = false): array
    {
        $gateways = CommercePlugin::getInstance()->getGateways()->getAllFrontEndGateways();

        return $asList ? ArrayHelper::map($gateways, 'id', 'name') : $gateways;
    }

    /**
     * Get all order statuses.
     *
     * @return OrderStatus[]
     */
    public function getOrderStatuses(): array
    {
        return CommercePlugin::getInstance()->getOrderStatuses()->getAllOrderStatuses();
    }

    /**
     * Get all available payment currencies.
     *
     * @return PaymentCurrency[]
     */
    public function getPaymentCurrencies(): array
    {
        $currencies = CommercePlugin::getInstance()->getPaymentCurrencies()->getAllPaymentCurrencies();

        return $currencies;
    }

    /**
     * Get all payment sources for the current user.
     *
     * @return PaymentSource[]
     */
    public function getPaymentSources(): array
    {
        $userId = Craft::$app->getUser()->getId();

        return CommercePlugin::getInstance()->getPaymentSources()->getAllPaymentSourcesByUserId((int)$userId);
    }

    /**
     * Get the plugin instance.
     *
     * @return CommercePlugin
     */
    public function getPlugin(): CommercePlugin
    {
        return CommercePlugin::getInstance();
    }

    /**
     * Get the primary payment currency.
     *
     * @return PaymentCurrency|null
     */
    public function getPrimaryPaymentCurrency()
    {
        return CommercePlugin::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrency();
    }

    /**
     * Get all product types.
     *
     * @return ProductType[]
     */
    public function getProductTypes(): array
    {
        return CommercePlugin::getInstance()->getProductTypes()->getAllProductTypes();
    }

    /**
     * Get all sales.
     *
     * @return Sale[]
     */
    public function getSales(): array
    {
        return CommercePlugin::getInstance()->getSales()->getAllSales();
    }

    /**
     * Returns all shipping categories.
     *
     * @return ShippingCategory[]
     */
    public function getShippingCategories(): array
    {
        return CommercePlugin::getInstance()->getShippingCategories()->getAllShippingCategories();
    }

    /**
     * Returns all tax categories.
     *
     * @return array|TaxCategory[]
     */
    public function getTaxCategories(): array
    {
        return CommercePlugin::getInstance()->getTaxCategories()->getAllTaxCategories();
    }

    /**
     * Get all shipping methods.
     *
     * @return ShippingMethod[]
     */
    public function getShippingMethods(): array
    {
        return CommercePlugin::getInstance()->getShippingMethods()->getAllShippingMethods();
    }

    /**
     * Get all shipping zones.
     *
     * @return ShippingZone[]
     */
    public function getShippingZones(): array
    {
        return CommercePlugin::getInstance()->getShippingZones()->getAllShippingZones();
    }

    /**
     * Return all states.
     *
     * @return State[]
     */
    public function getStates(): array
    {
        return CommercePlugin::getInstance()->getStates()->getAllStates();
    }

    /**
     * Return a 2D array of state names indexed by state ids, grouped by country ids.
     *
     * @return array [countryId => [id => name]]
     */
    public function getStatesArray(): array
    {
        return CommercePlugin::getInstance()->getStates()->getStatesGroupedByCountries();
    }

    /**
     * Get all tax rates.
     *
     * @return TaxRate[]
     */
    public function getTaxRates(): array
    {
        return CommercePlugin::getInstance()->getTaxRates()->getAllTaxRates();
    }

    /**
     * Get all tax zones.
     *
     * @return TaxZone[]
     */
    public function getTaxZones(): array
    {
        return CommercePlugin::getInstance()->getTaxZones()->getAllTaxZones();
    }

    /**
     * Returns a new OrderQuery instance.
     *
     * @param mixed $criteria
     *
     * @return OrderQuery
     */
    public function orders($criteria = null): OrderQuery
    {
        $query = Order::find();

        if ($criteria) {
            Craft::configure($query, $criteria);
        }

        return $query;
    }

    /**
     * Returns a new ProductQuery instance.
     *
     * @param mixed $criteria
     *
     * @return ProductQuery
     */
    public function products($criteria = null): ProductQuery
    {
        $query = Product::find();

        if ($criteria) {
            Craft::configure($query, $criteria);
        }

        return $query;
    }

    /**
     * Get Commerce settings.
     *
     * @return mixed
     */
    public function settings()
    {
        return CommercePlugin::getInstance()->getSettings();
    }

    /**
     * Returns a new VariantQuery instance.
     *
     * @param mixed $criteria
     *
     * @return VariantQuery
     */
    public function variants($criteria = null): VariantQuery
    {
        $query = Variant::find();

        if ($criteria) {
            Craft::configure($query, $criteria);
        }

        return $query;
    }
}
