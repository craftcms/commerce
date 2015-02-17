<?php

namespace Craft;

require 'vendor/autoload.php';

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
		$orderType = new Market_OrderTypeModel;
		$orderType->name = 'Normal Order';
		$orderType->handle = 'normalOrder';

		// Set the field layout
		$fieldLayout       = craft()->fields->assembleLayout([], []);
		$fieldLayout->type = 'Market_Order';
		$orderType->setFieldLayout($fieldLayout);

		craft()->market_orderType->save($orderType);

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
		return array(
			'market'                                                                                  => array('action' => 'market/dashboard/index'),

			'market/settings/global'                                                                  => array('action' => 'market/settings/edit'),

			'market/settings/taxcategories'                                                           => array('action' => 'market/taxCategory/index'),
			'market/settings/taxcategories/new'                                                       => array('action' => 'market/taxCategory/edit'),
			'market/settings/taxcategories/(?P<id>\d+)'                                               => array('action' => 'market/taxCategory/edit'),

			'market/settings/countries'                                                               => array('action' => 'market/country/index'),
			'market/settings/countries/new'                                                           => array('action' => 'market/country/edit'),
			'market/settings/countries/(?P<id>\d+)'                                                   => array('action' => 'market/country/edit'),

			'market/settings/states'                                                                  => array('action' => 'market/state/index'),
			'market/settings/states/new'                                                              => array('action' => 'market/state/edit'),
			'market/settings/states/(?P<id>\d+)'                                                      => array('action' => 'market/state/edit'),

			'market/settings/taxzones'                                                                => array('action' => 'market/taxZone/index'),
			'market/settings/taxzones/new'                                                            => array('action' => 'market/taxZone/edit'),
			'market/settings/taxzones/(?P<id>\d+)'                                                    => array('action' => 'market/taxZone/edit'),

			'market/settings/taxrates'                                                                => array('action' => 'market/taxRate/index'),
			'market/settings/taxrates/new'                                                            => array('action' => 'market/taxRate/edit'),
			'market/settings/taxrates/(?P<id>\d+)'                                                    => array('action' => 'market/taxRate/edit'),

			// Product Routes
			'market/products'                                                                         => array('action' => 'market/product/productIndex'),
			'market/products/(?P<productTypeHandle>{handle})/new'                                     => array('action' => 'market/product/editProduct'),
			'market/products/(?P<productTypeHandle>{handle})/(?P<productId>\d+)'                      => array('action' => 'market/product/editProduct'),
			'market/products/(?P<productTypeHandle>{handle})/(?P<productId>\d+)/variants/new'         => array('action' => 'market/variant/edit'),
			'market/products/(?P<productTypeHandle>{handle})/(?P<productId>\d+)/variants/(?P<id>\d+)' => array('action' => 'market/variant/edit'),

			'market/settings/producttypes'                                                            => array('action' => 'market/productType/index'),
			'market/settings/producttypes/(?P<productTypeId>\d+)'                                     => array('action' => 'market/productType/editProductType'),
			'market/settings/producttypes/new'                                                        => array('action' => 'market/productType/editProductType'),

			'market/settings/optiontypes'                                                             => array('action' => 'market/optionType/index'),
			'market/settings/optiontypes/(?P<optionTypeId>\d+)'                                       => array('action' => 'market/optionType/editOptionType'),
			'market/settings/optiontypes/new'                                                         => array('action' => 'market/optionType/editOptionType'),

			// Order Routes
			'market/orders'                                                                           => array('action' => 'market/order/orderIndex'),
			'market/orders/(?P<orderTypeHandle>{handle})/new'                                         => array('action' => 'market/order/editOrder'),
			'market/orders/(?P<orderTypeHandle>{handle})/(?P<orderId>\d+)'                            => array('action' => 'market/order/editOrder'),

			'market/settings/ordertypes'                                                              => array('action' => 'market/orderType/index'),
			'market/settings/ordertypes/(?P<orderTypeId>\d+)'                                         => array('action' => 'market/orderType/editorderType'),
			'market/settings/ordertypes/new'                                                          => array('action' => 'market/orderType/editOrderType'),

			'market/plans'                                                                            => array('action' => 'market/plans/index'),
			'market/charges'                                                                          => 'market/charges/index',
			'market/charges/(?P<chargeId>\d+)'                                                        => array('action' => 'market/charge/editCharge'),

			'market/settings/paymentmethods'                                                          => array('action' => 'market/paymentMethod/index'),
			'market/settings/paymentmethods/(?P<class>\w+)'                                           => array('action' => 'market/paymentMethod/edit'),
		);
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

}

