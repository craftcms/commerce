<?php
namespace Craft;

class MarketVariable
{
	/**
	 * Get Stripe Plans
	 *
	 * @return mixed
	 */
	public function plans()
	{
		return craft()->market_plans->getPlans();
	}

	/**
	 * Get Market settings
	 *
	 * @return mixed
	 */
	public function config()
	{
		return craft()->market_settings->getSettings();
	}

	/**
	 * @param array|null $criteria
	 *
	 * @return ElementCriteriaModel|null
	 */
	public function charges($criteria = NULL)
	{
		return craft()->elements->getCriteria('Market_Charge', $criteria);
	}

	/**
	 * @param array|null $criteria
	 *
	 * @return ElementCriteriaModel|null
	 */
	public function products($criteria = NULL)
	{
		return craft()->elements->getCriteria('Market_Product', $criteria);
	}

	/**
	 * @param array|null $criteria
	 *
	 * @return ElementCriteriaModel|null
	 */
	public function optionTypes()
	{
		return craft()->market_optionType->getAll();
	}

	/**
	 * @return Market_OrderModel
	 */
	public function getCart()
	{
		return craft()->market_order->getCart();
	}

	/**
	 * @return Market_CustomerModel
	 */
	public function getCustomer()
	{
		return craft()->market_customer->getCustomer();
	}
}