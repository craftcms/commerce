<?php
namespace Craft;

/**
 * Variable class.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
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
    public function orders($criteria = null)
    {

        if (!isset($criteria['dateOrdered'])) {
            $criteria['dateOrdered'] = ':notempty:';
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
     * @return array [id => name]
     */
    public function getCountriesList()
    {
        return craft()->commerce_countries->getFormList();
    }

    /**
     * @return array
     */
    public function getStatesArray()
    {
        return craft()->commerce_states->getGroupedByCountries();
    }

    /**
     * @return array
     */
    public function getShippingMethods()
    {
        $cart = craft()->commerce_cart->getCart();

        return craft()->commerce_shippingMethods->calculateForCart($cart);
    }

    /**
     * @return array
     */
    public function getPaymentMethods()
    {
        $methods = craft()->commerce_paymentMethods->getAllForFrontend();

        return \CHtml::listData($methods, 'id', 'name');
    }

    /**
     * @return array
     */
    public function getOrderStatuses()
    {
        return array_map(function ($status) {
            return $status->attributes;
        }, craft()->commerce_orderStatuses->getAll());
    }

    /**
     * @return Commerce_DiscountModel[]
     */
    public function getDiscounts()
    {
        $discounts = craft()->commerce_discounts->getAll();

        return $discounts;
    }

    /**
     * @return Commerce_SaleModel[]
     */
    public function getSales()
    {
        $sales = craft()->commerce_sales->getAll();

        return $sales;
    }
}
