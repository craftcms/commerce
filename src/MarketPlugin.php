<?php

namespace Craft;

require 'vendor/autoload.php';

use Market\Extensions\MarketTwigExtension;
use Market\Market;

class MarketPlugin extends BasePlugin
{
	public $handle = 'market';

	function init()
	{

//        Market::app()["stripe"] = function ($c) {
//            $key = $this->getSettings()->secretKey;
//
//            return new Stripe($key);
//        };
        Market::app()["hashids"] = function ($c) {
			$len = craft()->config->get('orderNumberLength', $this->handle);
			$alphabet = craft()->config->get('orderNumberAlphabet', $this->handle);
			return new \Hashids\Hashids("market",$len,$alphabet);
		};
	}

	public function getName()
	{
		return "Market";
	}

	public function getVersion()
	{
		return "0.0.1";
	}

	public function getDeveloper()
	{
		return "Make with Morph (Luke Holder)";
	}

	public function getDeveloperUrl()
	{
		return "http://makewithmorph.com";
	}

	public function hasCpSection()
	{
		return true;
	}

	/**
	 * Creating default order type
	 *
	 * @throws Exception
	 * @throws \Exception
	 */
	public function onAfterInstall()
	{
        craft()->market_seed->afterInstall();

        if(craft()->config->get('devMode')) {
            craft()->market_seed->testData();
        }

//        $fieldLayout = array('type' => 'Market_Charge');
//        $fieldLayout = FieldLayoutModel::populateModel($fieldLayout);
//        craft()->fields->saveLayout($fieldLayout);
	}

	public function onBeforeUninstall()
	{
//        $fieldLayout = array('type' => 'Market_Charge');
//        $fieldLayout = FieldLayoutModel::populateModel($fieldLayout);
//        craft()->fields->saveLayout($fieldLayout);
	}

	public function registerCpRoutes()
	{
		return [
			'market'                                                                                  => ['action' => 'market/dashboard/index'],

			'market/settings/global'                                                                  => ['action' => 'market/settings/edit'],

			'market/settings/taxcategories'                                                           => ['action' => 'market/taxCategory/index'],
			'market/settings/taxcategories/new'                                                       => ['action' => 'market/taxCategory/edit'],
			'market/settings/taxcategories/(?P<id>\d+)'                                               => ['action' => 'market/taxCategory/edit'],

			'market/settings/countries'                                                               => ['action' => 'market/country/index'],
			'market/settings/countries/new'                                                           => ['action' => 'market/country/edit'],
			'market/settings/countries/(?P<id>\d+)'                                                   => ['action' => 'market/country/edit'],

			'market/settings/states'                                                                  => ['action' => 'market/state/index'],
			'market/settings/states/new'                                                              => ['action' => 'market/state/edit'],
			'market/settings/states/(?P<id>\d+)'                                                      => ['action' => 'market/state/edit'],

			'market/settings/taxzones'                                                                => ['action' => 'market/taxZone/index'],
			'market/settings/taxzones/new'                                                            => ['action' => 'market/taxZone/edit'],
			'market/settings/taxzones/(?P<id>\d+)'                                                    => ['action' => 'market/taxZone/edit'],

			'market/settings/taxrates'                                                                => ['action' => 'market/taxRate/index'],
			'market/settings/taxrates/new'                                                            => ['action' => 'market/taxRate/edit'],
			'market/settings/taxrates/(?P<id>\d+)'                                                    => ['action' => 'market/taxRate/edit'],

			// Product Routes
			'market/products'                                                                         => ['action' => 'market/product/productIndex'],
			'market/products/(?P<productTypeHandle>{handle})/new'                                     => ['action' => 'market/product/editProduct'],
			'market/products/(?P<productTypeHandle>{handle})/(?P<productId>\d+)'                      => ['action' => 'market/product/editProduct'],
			'market/products/(?P<productTypeHandle>{handle})/(?P<productId>\d+)/variants/new'         => ['action' => 'market/variant/edit'],
			'market/products/(?P<productTypeHandle>{handle})/(?P<productId>\d+)/variants/(?P<id>\d+)' => ['action' => 'market/variant/edit'],

			'market/settings/producttypes'                                                            => ['action' => 'market/productType/index'],
			'market/settings/producttypes/(?P<productTypeId>\d+)'                                     => ['action' => 'market/productType/editProductType'],
			'market/settings/producttypes/new'                                                        => ['action' => 'market/productType/editProductType'],

			'market/settings/optiontypes'                                                             => ['action' => 'market/optionType/index'],
			'market/settings/optiontypes/(?P<optionTypeId>\d+)'                                       => ['action' => 'market/optionType/editOptionType'],
			'market/settings/optiontypes/new'                                                         => ['action' => 'market/optionType/editOptionType'],

			// Order Routes
			'market/orders'                                                                           => ['action' => 'market/order/orderIndex'],
			'market/orders/(?P<orderTypeHandle>{handle})/new'                                         => ['action' => 'market/order/editOrder'],
			'market/orders/(?P<orderTypeHandle>{handle})/(?P<orderId>\d+)'                            => ['action' => 'market/order/editOrder'],

			'market/settings/ordertypes'                                                              => ['action' => 'market/orderType/index'],
			'market/settings/ordertypes/(?P<orderTypeId>\d+)'                                         => ['action' => 'market/orderType/editorderType'],
			'market/settings/ordertypes/new'                                                          => ['action' => 'market/orderType/editOrderType'],

			'market/plans'                                                                            => ['action' => 'market/plans/index'],
			'market/charges'                                                                          => 'market/charges/index',
			'market/charges/(?P<chargeId>\d+)'                                                        => ['action' => 'market/charge/editCharge'],

			'market/settings/paymentmethods'                                                          => ['action' => 'market/paymentMethod/index'],
			'market/settings/paymentmethods/(?P<class>\w+)'                                           => ['action' => 'market/paymentMethod/edit'],

            'market/settings/sales'                                                                   => ['action' => 'market/sale/index'],
            'market/settings/sales/new'                                                               => ['action' => 'market/sale/edit'],
            'market/settings/sales/(?P<id>\d+)'                                                       => ['action' => 'market/sale/edit'],

            'market/settings/discounts'                                                               => ['action' => 'market/discount/index'],
            'market/settings/discounts/new'                                                           => ['action' => 'market/discount/edit'],
            'market/settings/discounts/(?P<id>\d+)'                                                   => ['action' => 'market/discount/edit'],
		];
	}

	public function registerSiteRoutes()
	{
		return [
			'add-to-cart' => ['action' => 'market/cart/add'],
		];
	}

	/**
	 * @return array
	 */
	protected function defineSettings()
	{
		return array(
			'secretKey'       => AttributeType::String,
			'publishableKey'  => AttributeType::String,
			//TODO: Fill currency enum values dynamically based on https://support.stripe.com/questions/which-currencies-does-stripe-support
			'defaultCurrency' => AttributeType::String
		);
	}

	/**
	 * Adding our custom twig functionality
	 * @return MarketTwigExtension
	 */
	public function addTwigExtension()
	{
		return new MarketTwigExtension;
	}

}

