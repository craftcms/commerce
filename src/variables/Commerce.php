<?php

namespace craft\commerce\variables;

use Craft;
use craft\base\Model;
use craft\commerce\base\Gateway;
use craft\commerce\elements\db\OrderQuery;
use craft\commerce\elements\db\ProductQuery;
use craft\commerce\elements\db\VariantQuery;
use craft\commerce\elements\Order;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\commerce\models\Currency;
use craft\commerce\models\Customer;
use craft\commerce\models\Discount;
use craft\commerce\models\OrderStatus;
use craft\commerce\models\PaymentCurrency;
use craft\commerce\models\ProductType;
use craft\commerce\models\Sale;
use craft\commerce\models\ShippingMethod;
use craft\commerce\models\ShippingZone;
use craft\commerce\models\TaxCategory;
use craft\commerce\models\TaxRate;
use craft\commerce\models\TaxZone;
use craft\commerce\services\Addresses;
use craft\helpers\ArrayHelper;

/**
 * Variable class.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.variables
 * @since     1.0
 */
class Commerce
{
    // Public Methods
    // =========================================================================

    /**
     * @return Commerce
     */
    public function getPlugin()
    {
        return Commerce::getInstance();
    }

    /**
     * Get Commerce settings
     *
     * @return mixed
     */
    public function settings()
    {
        return Commerce::getInstance()->getSettings();
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
     * @return Order
     */
    public function getCart()
    {
        return Commerce::getInstance()->getCart()->getCart();
    }

    /**
     * @return Customer
     */
    public function getCustomer()
    {
        return Commerce::getInstance()->getCustomers()->getCustomer();
    }

    /**
     * @return array
     */
    public function getCountries()
    {
        return Commerce::getInstance()->getCountries()->getAllCountries();
    }

    /**
     * @return Addresses
     */
    public function getAddresses()
    {
        return Commerce::getInstance()->getAddresses();
    }

    /**
     * @return array
     */
    public function getStates()
    {
        return Commerce::getInstance()->getStates()->getAllStates();
    }

    /**
     * @return array [id => name]
     */
    public function getCountriesList()
    {
        return Commerce::getInstance()->getCountries()->getAllCountriesListData();
    }

    /**
     * @return array [countryId => [id => name]]
     */
    public function getStatesArray()
    {
        return Commerce::getInstance()->getStates()->getStatesGroupedByCountries();
    }

    /**
     * @return array
     */
    public function getAvailableShippingMethods()
    {
        $cart = Commerce::getInstance()->getCart()->getCart();

        return Commerce::getInstance()->getShippingMethods()->getOrderedAvailableShippingMethods($cart);
    }

    /**
     * @return ShippingMethod[]
     */
    public function getShippingMethods()
    {
        return Commerce::getInstance()->getShippingMethods()->getAllShippingMethods();
    }

    /**
     * @return ShippingZone[]
     */
    public function getShippingZones()
    {
        return Commerce::getInstance()->getShippingZones()->getAllShippingZones();
    }

    /**
     * @param bool $asList should the categories be returned as a simple list suitable for a html select box
     *
     * @return array
     */
    public function getShippingCategories($asList = false)
    {
        $shippingCategories = Commerce::getInstance()->getShippingCategories()->getAllShippingCategories();

        if ($asList) {
            return ArrayHelper::map($shippingCategories, 'id', 'name');
        }

        // Need to put the methods into an array keyed by method ID for backwards compatibility.
        return $this->_arrayKeyedByAttribute($shippingCategories, 'id');
    }

    /**
     * TODO Move this into an array
     *
     * @param Model[] $array     All models using this method must implement __string() to be backwards compatible with ArrayHelper::map
     * @param string      $attribute The attribute you want the array keyed by.
     *
     * @return array
     */
    private function _arrayKeyedByAttribute($array, $attribute)
    {
        $newArray = [];
        foreach ($array as $model) {
            $newArray[$model->{$attribute}] = $model;
        }

        return $newArray;
    }

    /**
     * @param bool $asList Whether we should return the payment methods as a simple list suitable for a html select box
     *
     * @return array|Gateway[]
     */
    public function getGateways($asList = false)
    {
        $methods = Commerce::getInstance()->getGateways()->getAllFrontEndGateways();

        if ($asList) {
            return ArrayHelper::map($methods, 'id', 'name');
        }

        // Need to put the methods into an array keyed by method ID for backwards compatibility.
        return $this->_arrayKeyedByAttribute($methods, 'id');
    }

    /**
     * @return ProductType[]
     */
    public function getProductTypes()
    {
        return Commerce::getInstance()->getProductTypes()->getAllProductTypes();
    }

    /**
     * @return OrderStatus[]
     */
    public function getOrderStatuses()
    {
        return Commerce::getInstance()->getOrderStatuses()->getAllOrderStatuses();
    }

    /**
     * @param bool $asList should the categories be returned as a simple list suitable for a html select box
     *
     * @return array|TaxCategory[]
     */
    public function getTaxCategories($asList = false)
    {
        $taxCategories = Commerce::getInstance()->getTaxCategories()->getAllTaxCategories();

        if ($asList) {
            return ArrayHelper::map($taxCategories, 'id', 'name');
        }

        // Need to put the methods into an array keyed by method ID for backwards compatibility.
        return $this->_arrayKeyedByAttribute($taxCategories, 'id');
    }

    /**
     * @return TaxRate[]
     */
    public function getTaxRates()
    {
        return Commerce::getInstance()->getTaxRates()->getAllTaxRates();
    }

    /**
     * @return TaxZone[]
     */
    public function getTaxZones()
    {
        return Commerce::getInstance()->getTaxZones()->getAllTaxZones();
    }

    /**
     * @return Discount[]
     */
    public function getDiscounts()
    {
        return Commerce::getInstance()->getDiscounts()->getAllDiscounts();
    }

    /**
     * @param string $code
     *
     * @return Discount|null
     */
    public function getDiscountByCode($code)
    {
        return Commerce::getInstance()->getDiscounts()->getDiscountByCode($code);
    }

    /**
     * @return Sale[]
     */
    public function getSales()
    {
        return Commerce::getInstance()->getSales()->getAllSales();
    }

    /**
     * @return PaymentCurrency[]
     */
    public function getPaymentCurrencies()
    {
        $currencies = Commerce::getInstance()->getPaymentCurrencies()->getAllPaymentCurrencies();

        return $currencies;
    }

    /**
     * @return Currency[]
     */
    public function getCurrencies()
    {
        return Commerce::getInstance()->getCurrencies()->getAllCurrencies();
    }

    // Private Methods
    // =========================================================================

    /**
     * @return PaymentCurrency
     */
    public function getPrimaryPaymentCurrency()
    {
        return Commerce::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrency();
    }
}
