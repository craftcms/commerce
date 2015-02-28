<?php
namespace Craft;

class MarketVariable
{

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
	public function products($criteria = NULL)
	{
		return craft()->elements->getCriteria('Market_Product', $criteria);
	}

	/**
	 * @param array|null $criteria
	 *
	 * @return ElementCriteriaModel|null
	 */
	public function orders($criteria = NULL)
	{
		return craft()->elements->getCriteria('Market_Order', $criteria);
	}

	/**
	 * @return Market_ProductModel[]
	 */
	public function productsWithSales()
	{
		return craft()->market_product->getAllWithSales();
	}


	public function optionTypes()
	{
		return craft()->market_optionType->getAll();
	}

	/**
	 * @return Market_OrderModel
	 */
	public function getCart()
	{
		return craft()->market_cart->getCart();
	}

	/**
	 * @return Market_CustomerModel
	 */
	public function getCustomer()
	{
		return craft()->market_customer->getCustomer();
	}

	/**
	 * @return array [id => name]
	 */
	public function getCountriesList()
	{
		return craft()->market_country->getFormList();
	}

	/**
	 * @return array
	 */
	public function getStatesArray()
	{
		return craft()->market_state->getGroupedByCountries();
	}

	public function getShippingMethods()
	{
		return craft()->market_shippingMethod->calculateForCart();
	}

	public function getPaymentMethods()
	{
		$methods = craft()->market_paymentMethod->getAllForFrontend();

		return \CHtml::listData($methods, 'id', 'name');
	}

	/**
	 * A way to use form.* macros in our templates
	 *
	 * @param string $macro
	 * @param array  $args
	 *
	 * @return \Twig_Markup
	 */
	public function renderFormMacro($macro, array $args)
	{
		// Get the current template path
		$originalPath = craft()->path->getTemplatesPath();

		// Point Twig at the CP templates
		craft()->path->setTemplatesPath(craft()->path->getCpTemplatesPath());

		// Render the macro.
		$html = craft()->templates->renderMacro('_includes/forms', $macro, [$args]);

		// Restore the original template path
		craft()->path->setTemplatesPath($originalPath);

		return TemplateHelper::getRaw($html);
	}
}