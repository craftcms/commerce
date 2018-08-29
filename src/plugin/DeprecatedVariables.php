<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\plugin;

use Craft;
use craft\commerce\elements\Order;
use craft\commerce\models\Customer;

/**
 * Trait Deprecated Variables
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
trait DeprecatedVariables
{
    /**
     * Returns all available shipping methods for the current cart.
     *
     * @return array
     * @deprecated as of 2.0
     */
    public function getAvailableShippingMethods(): array
    {
        Craft::$app->getDeprecator()->log('craft.commerce.availableShippingMethods', 'craft.commerce.availableShippingMethods has been deprecated. Use craft.commerce.shippingMethods.availableShippingMethods(cart) instead');

        $cart = $this->getCarts()->getCart();

        return $cart->getAvailableShippingMethods();
    }

    /**
     * Returns the current Cart.
     *
     * @return Order
     * @deprecated as of 2.0
     */
    public function getCart(): Order
    {
        Craft::$app->getDeprecator()->log('craft.commerce.cart', 'craft.commerce.cart has been deprecated. Use craft.commerce.carts.cart instead');

        return $this->getCarts()->getCart();
    }

    /**
     * Return an array of country names, indexed by ID.
     *
     * @return array [id => name]
     * @deprecated as of 2.0
     */
    public function getCountriesList(): array
    {
        Craft::$app->getDeprecator()->log('craft.commerce.countriesList', 'craft.commerce.countriesList has been deprecated. Use craft.commerce.countries.allCountriesAsList instead');

        return $this->getCountries()->getAllCountriesAsList();
    }

    /**
     * Returns the current customer.
     *
     * @return Customer
     * @deprecated as of 2.0
     */
    public function getCustomer(): Customer
    {
        Craft::$app->getDeprecator()->log('craft.commerce.customer', 'craft.commerce.customer has been deprecated. Use craft.commerce.customers.customer instead');

        return $this->getCustomers()->getCustomer();
    }

    /**
     * Returns a discount by its code.
     *
     * @param string $code the discount code
     * @return Discount|null
     * @deprecated as of 2.0
     */
    public function getDiscountByCode($code)
    {
        Craft::$app->getDeprecator()->log('craft.commerce.discountByCode', 'craft.commerce.discountByCode has been deprecated. Use craft.commerce.discounts.discountByCode instead');

        return $this->getDiscounts()->getDiscountByCode($code);
    }

    /**
     * Returns the primary payment currency.
     *
     * @return PaymentCurrency|null
     * @deprecated as of 2.0
     */
    public function getPrimaryPaymentCurrency()
    {
        Craft::$app->getDeprecator()->log('craft.commerce.primaryPaymentCurrency', 'craft.commerce.primaryPaymentCurrency has been deprecated. Use craft.commerce.paymentCurrencies.primaryPaymentCurrency instead');

        return $this->getPaymentCurrencies()->getPrimaryPaymentCurrency();
    }

    /**
     * Returns a 2D array of state names indexed by state ids, grouped by country ids.
     *
     * @return array [countryId => [id => name]]
     * @deprecated as of 2.0
     */
    public function getStatesArray(): array
    {
        Craft::$app->getDeprecator()->log('craft.commerce.statesArray', 'craft.commerce.statesArray has been deprecated. Use craft.commerce.states.allStatesAsList instead');

        return $this->getStates()->getAllStatesAsList();
    }
}
