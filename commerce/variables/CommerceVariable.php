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
	 * @param bool $asList Whether we should return the payment methods as a simple list suitable for a html select box
	 * @return Commerce_PaymentMethodModel[] array
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
	 * @param bool $asList Whether we should return the tax categories as a simple list suitable for a html select box
	 * @return Commerce_TaxCategoryModel[] array
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
	 * @return Commerce_PaymentCurrencyModel[]
	 */
	public function getCurrencies()
	{
		$currencies = craft()->commerce_currencies->getAllCurrencies();

		return $currencies;
	}

	/**
	 * @return Commerce_PaymentCurrencyModel
	 */
	public function getDefaultCurrency()
	{
		$currency = craft()->commerce_paymentCurrencies->getDefaultPaymentCurrency();

		return $currency;
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
