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
	 * @return array
	 */
	public function getStatesArray()
	{
		return craft()->commerce_states->getStatesGroupedByCountries();
	}

	/**
	 * @return ShippingMethod[] array
	 */
	public function getAvailableShippingMethods()
	{
		$cart = craft()->commerce_cart->getCart();

		return craft()->commerce_shippingMethods->getOrderedAvailableShippingMethods($cart);
	}

	/**
	 * @return Commerce_PaymentMethodModel[] array
	 */
	public function getPaymentMethods()
	{
		$methods = craft()->commerce_paymentMethods->getAllFrontEndPaymentMethods();

		// Need to put the methods into an array keyed by method ID for backwards compatibility.
		return $this->arrayKeyedByAttribute($methods, 'id');
	}

	/**
	 * @return Commerce_ProductTypeModel[] array
	 */
	public function getProductTypes()
	{
		return craft()->commerce_productTypes->getAllProductTypes();
	}

	/**
	 * @return Commerce_OrderStatusModel[] array
	 */
	public function getOrderStatuses()
	{
		return craft()->commerce_orderStatuses->getAllOrderStatuses();
	}

	/**
	 * @return Commerce_TaxCategoryModel[] array
	 */
	public function getTaxCategories()
	{
		$taxCategories = craft()->commerce_taxCategories->getAllTaxCategories();

		// Need to put the methods into an array keyed by method ID for backwards compatibility.
		return $this->arrayKeyedByAttribute($taxCategories, 'id');
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
	 * @return Commerce_CurrencyModel[]
	 */
	public function getCurrencies()
	{
		$currencies = craft()->commerce_currencies->getAllCurrencies();

		return $currencies;
	}

	/**
	 * @return Commerce_CurrencyModel
	 */
	public function getDefaultCurrency()
	{
		$currency = craft()->commerce_currencies->getDefaultCurrency();

		return $currency;
	}

	// Private Methods
	// =========================================================================

	/**
	 * TODO Move this into an array helper?
	 *
	 * @param BaseModel[] $array
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
