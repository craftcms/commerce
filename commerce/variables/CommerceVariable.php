<?php
namespace Craft;

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
        return craft()->commerce_settings->getSettings();
    }

    /**
     * @param array|null $criteria
     *
     * @return ElementCriteriaModel|null
     */
    public function products($criteria = null)
    {
        return craft()->elements->getCriteria('Commerce_Product', $criteria);
    }

    /**
     * @param array|null $criteria
     *
     * @return ElementCriteriaModel|null
     */
    public function variants($criteria = null)
    {
        return craft()->elements->getCriteria('Commerce_Variant', $criteria);
    }

    /**
     * @param array|null $criteria
     *
     * @return ElementCriteriaModel|null
     */
    public function orders($criteria = null)
    {

        if (!isset($criteria['isCompleted']))
        {
            $criteria['isCompleted'] = true;
        }

        return craft()->elements->getCriteria('Commerce_Order', $criteria);
    }

    /**
     * @return Commerce_OrderModel
     */
    public function getCart()
    {
        return craft()->commerce_cart->getCart();
    }

    /**
     * @return Commerce_CustomerModel
     */
    public function getCustomer()
    {
        return craft()->commerce_customers->getCustomer();
    }

    /**
     * @return array
     */
    public function getCountries()
    {
        return craft()->commerce_countries->getAllCountries();
    }

    /**
     * @return array
     */
    public function getStates()
    {
        return craft()->commerce_states->getAllStates();
    }

    /**
     * @return array [id => name]
     */
    public function getCountriesList()
    {
        return craft()->commerce_countries->getAllCountriesListData();
    }

    /**
     * @return array [countryId => [id => name]]
     */
    public function getStatesArray()
    {
        return craft()->commerce_states->getStatesGroupedByCountries();
    }

    /**
     * @return array
     */
    public function getAvailableShippingMethods()
    {
        $cart = craft()->commerce_cart->getCart();

        return craft()->commerce_shippingMethods->getOrderedAvailableShippingMethods($cart);
    }

    /**
     * @return Commerce_ShippingMethodModel[]
     */
    public function getShippingMethods()
    {
        return craft()->commerce_shippingMethods->getAllShippingMethods();
    }

    /**
     * @return Commerce_ShippingZoneModel[]
     */
    public function getShippingZones()
    {
        return craft()->commerce_shippingZones->getAllShippingZones();
    }

    /**
     * @param bool $asList should the categories be returned as a simple list suitable for a html select box
     *
     * @return array
     */
    public function getShippingCategories($asList = false)
    {
        $shippingCategories = craft()->commerce_shippingCategories->getAllShippingCategories();

        if ($asList)
        {
            return \CHtml::listData($shippingCategories, 'id', 'name');
        }

        // Need to put the methods into an array keyed by method ID for backwards compatibility.
        return $this->arrayKeyedByAttribute($shippingCategories, 'id');
    }

    /**
     * @param bool $asList Whether we should return the payment methods as a simple list suitable for a html select box
     *
     * @return array|Commerce_PaymentMethodModel[]
     */
    public function getPaymentMethods($asList = false)
    {
        $methods = craft()->commerce_paymentMethods->getAllFrontEndPaymentMethods();

        if ($asList)
        {
            return \CHtml::listData($methods, 'id', 'name');
        }

        // Need to put the methods into an array keyed by method ID for backwards compatibility.
        return $this->arrayKeyedByAttribute($methods, 'id');
    }

    /**
     * @return Commerce_ProductTypeModel[]
     */
    public function getProductTypes()
    {
        return craft()->commerce_productTypes->getAllProductTypes();
    }

    /**
     * @return Commerce_OrderStatusModel[]
     */
    public function getOrderStatuses()
    {
        return craft()->commerce_orderStatuses->getAllOrderStatuses();
    }

    /**
     * @param bool $asList should the categories be returned as a simple list suitable for a html select box
     *
     * @return array|Commerce_TaxCategoryModel[]
     */
    public function getTaxCategories($asList = false)
    {
        $taxCategories = craft()->commerce_taxCategories->getAllTaxCategories();

        if ($asList)
        {
            return \CHtml::listData($taxCategories, 'id', 'name');
        }

        // Need to put the methods into an array keyed by method ID for backwards compatibility.
        return $this->arrayKeyedByAttribute($taxCategories, 'id');
    }

    /**
     * @return Commerce_TaxRateModel[]
     */
    public function getTaxRates()
    {
        return craft()->commerce_taxRates->getAllTaxRates();
    }

    /**
     * @return Commerce_TaxZoneModel[]
     */
    public function getTaxZones()
    {
        return craft()->commerce_taxZones->getAllTaxZones();
    }

    /**
     * @return Commerce_DiscountModel[]
     */
    public function getDiscounts()
    {
        $discounts = craft()->commerce_discounts->getAllDiscounts();

        return $discounts;
    }

    /**
     * @param string $code
     *
     * @return Commerce_DiscountModel|null
     */
    public function getDiscountByCode($code)
    {
        $discount = craft()->commerce_discounts->getDiscountByCode($code);

        return $discount;
    }

    /**
     * @return Commerce_SaleModel[]
     */
    public function getSales()
    {
        $sales = craft()->commerce_sales->getAllSales();

        return $sales;
    }

    /**
     * @return Commerce_PaymentCurrencyModel[]
     */
    public function getPaymentCurrencies()
    {
        $currencies = craft()->commerce_paymentCurrencies->getAllPaymentCurrencies();

        return $currencies;
    }

    /**
     * @return Commerce_CurrencyModel[]
     */
    public function getCurrencies()
    {
        $currencies = craft()->commerce_currencies->getAllCurrencies();

        return $currencies;
    }

    /**
     * @return Commerce_PaymentCurrencyModel
     */
    public function getPrimaryPaymentCurrency()
    {
        return craft()->commerce_paymentCurrencies->getPrimaryPaymentCurrency();
    }

    // Private Methods
    // =========================================================================

    /**
     * TODO Move this into an array
     *
     * @param BaseModel[] $array All models using this method must implement __string() to be backwards compatible with \CHtml::listData
     * @param string      $attribute The attribute you want the array keyed by.
     *
     * @return array
     */
    private function arrayKeyedByAttribute($array, $attribute)
    {
        $newArray = [];
        foreach ($array as $model)
        {
            $newArray[$model->{$attribute}] = $model;
        }

        return $newArray;
    }
}
