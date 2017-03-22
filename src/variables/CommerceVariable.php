<?php
namespace Craft;

use craft\commerce\fieldtypes\Customer;
use craft\commerce\models\ShippingZone;
use craft\commerce\models\TaxCategory;
use craft\commerce\models\TaxRate;
use craft\commerce\models\TaxZone;
use craft\commerce\Plugin;
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
class CommerceVariable
{
    /**
     * Get Commerce settings
     *
     * @return mixed
     */
    public function settings()
    {
        return Plugin::getInstance()->getSettings()->getSettings();
    }

    /**
     * @param array|null $criteria
     *
     * @return ElementCriteriaModel|null
     */
    public function products($criteria = null)
    {
        return Craft::$app->getElements()->getCriteria('Commerce_Product', $criteria);
    }

    /**
     * @param array|null $criteria
     *
     * @return ElementCriteriaModel|null
     */
    public function variants($criteria = null)
    {
        return Craft::$app->getElements()->getCriteria('Commerce_Variant', $criteria);
    }

    /**
     * @param array|null $criteria
     *
     * @return ElementCriteriaModel|null
     */
    public function orders($criteria = null)
    {

        if (!isset($criteria['isCompleted'])) {
            $criteria['isCompleted'] = true;
        }

        return Craft::$app->getElements()->getCriteria('Commerce_Order', $criteria);
    }

    /**
     * @return Order
     */
    public function getCart()
    {
        return Plugin::getInstance()->getCart()->getCart();
    }

    /**
     * @return Customer
     */
    public function getCustomer()
    {
        return Plugin::getInstance()->getCustomers()->getCustomer();
    }

    /**
     * @return array
     */
    public function getCountries()
    {
        return Plugin::getInstance()->getCountries()->getAllCountries();
    }

    /**
     * @return array
     */
    public function getStates()
    {
        return Plugin::getInstance()->getStates()->getAllStates();
    }

    /**
     * @return array [id => name]
     */
    public function getCountriesList()
    {
        return Plugin::getInstance()->getCountries()->getAllCountriesListData();
    }

    /**
     * @return array [countryId => [id => name]]
     */
    public function getStatesArray()
    {
        return Plugin::getInstance()->getStates()->getStatesGroupedByCountries();
    }

    /**
     * @return array
     */
    public function getAvailableShippingMethods()
    {
        $cart = Plugin::getInstance()->getCart()->getCart();

        return Plugin::getInstance()->getShippingMethods()->getOrderedAvailableShippingMethods($cart);
    }

    /**
     * @return ShippingMethod[]
     */
    public function getShippingMethods()
    {
        return Plugin::getInstance()->getShippingMethods()->getAllShippingMethods();
    }

    /**
     * @return ShippingZone[]
     */
    public function getShippingZones()
    {
        return Plugin::getInstance()->getShippingZones()->getAllShippingZones();
    }

    /**
     * @param bool $asList should the categories be returned as a simple list suitable for a html select box
     *
     * @return array
     */
    public function getShippingCategories($asList = false)
    {
        $shippingCategories = Plugin::getInstance()->getShippingCategories()->getAllShippingCategories();

        if ($asList) {
            return ArrayHelper::map($shippingCategories, 'id', 'name');
        }

        // Need to put the methods into an array keyed by method ID for backwards compatibility.
        return $this->arrayKeyedByAttribute($shippingCategories, 'id');
    }

    /**
     * TODO Move this into an array
     *
     * @param BaseModel[] $array     All models using this method must implement __string() to be backwards compatible with ArrayHelper::map
     * @param string      $attribute The attribute you want the array keyed by.
     *
     * @return array
     */
    private function arrayKeyedByAttribute($array, $attribute)
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
     * @return array|PaymentMethod[]
     */
    public function getPaymentMethods($asList = false)
    {
        $methods = Plugin::getInstance()->getPaymentMethods()->getAllFrontEndPaymentMethods();

        if ($asList) {
            return ArrayHelper::map($methods, 'id', 'name');
        }

        // Need to put the methods into an array keyed by method ID for backwards compatibility.
        return $this->arrayKeyedByAttribute($methods, 'id');
    }

    /**
     * @return ProductType[]
     */
    public function getProductTypes()
    {
        return Plugin::getInstance()->getProductTypes()->getAllProductTypes();
    }

    /**
     * @return OrderStatus[]
     */
    public function getOrderStatuses()
    {
        return Plugin::getInstance()->getOrderStatuses()->getAllOrderStatuses();
    }

    /**
     * @param bool $asList should the categories be returned as a simple list suitable for a html select box
     *
     * @return array|TaxCategory[]
     */
    public function getTaxCategories($asList = false)
    {
        $taxCategories = Plugin::getInstance()->getTaxCategories()->getAllTaxCategories();

        if ($asList) {
            return ArrayHelper::map($taxCategories, 'id', 'name');
        }

        // Need to put the methods into an array keyed by method ID for backwards compatibility.
        return $this->arrayKeyedByAttribute($taxCategories, 'id');
    }

    /**
     * @return TaxRate[]
     */
    public function getTaxRates()
    {
        return Plugin::getInstance()->getTaxRates()->getAllTaxRates();
    }

    /**
     * @return TaxZone[]
     */
    public function getTaxZones()
    {
        return Plugin::getInstance()->getTaxZones()->getAllTaxZones();
    }

    /**
     * @return Discount[]
     */
    public function getDiscounts()
    {
        $discounts = Plugin::getInstance()->getDiscounts()->getAllDiscounts();

        return $discounts;
    }

    /**
     * @param string $code
     *
     * @return Discount|null
     */
    public function getDiscountByCode($code)
    {
        $discount = Plugin::getInstance()->getDiscounts()->getDiscountByCode($code);

        return $discount;
    }

    /**
     * @return Sale[]
     */
    public function getSales()
    {
        $sales = Plugin::getInstance()->getSales()->getAllSales();

        return $sales;
    }

    /**
     * @return PaymentCurrency[]
     */
    public function getPaymentCurrencies()
    {
        $currencies = Plugin::getInstance()->getPaymentCurrencies()->getAllPaymentCurrencies();

        return $currencies;
    }

    /**
     * @return Currency[]
     */
    public function getCurrencies()
    {
        $currencies = Plugin::getInstance()->getCurrencies()->getAllCurrencies();

        return $currencies;
    }

    // Private Methods
    // =========================================================================

    /**
     * @return PaymentCurrency
     */
    public function getPrimaryPaymentCurrency()
    {
        return Plugin::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrency();
    }
}
