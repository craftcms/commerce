<?php

namespace Craft;

use Market\Extensions\MarketTwigExtension;

require 'vendor/autoload.php';
//define('DOMPDF_ENABLE_AUTOLOAD', false);
//require_once 'vendor/dompdf/dompdf/dompdf_config.inc.php';


//use Market\Market;

class MarketPlugin extends BasePlugin
{
	public $handle = 'market';

	function init()
	{
		$this->initMarketNav();

        //init global event handlers
        craft()->on('market_orderHistory.onStatusChange',
			[
				craft()->market_orderStatus, 'statusChangeHandler'
			]
		);

		craft()->on('market_order.onOrderComplete',
			[
				craft()->market_discount, 'orderCompleteHandler'
			]
		);

	}

	public function getName()
	{
		return "Market";
	}

	public function getVersion()
	{
		return file_get_contents(__DIR__.DIRECTORY_SEPARATOR."VERSION.txt");
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

	public function onAfterInstall()
	{
		craft()->market_seed->afterInstall();

		if (craft()->config->get('devMode')) {
			craft()->market_seed->testData();
		}

	}

	public function onBeforeUninstall()
	{

	}

	public function modifyCpNav(&$nav)
	{
		if (craft()->userSession->isAdmin())
		{
			$nav['market'] = array('label' => 'Market', 'url' => 'market');
		}
	}


	public function addCommands() {
		return require(__DIR__.DIRECTORY_SEPARATOR.'commands.php');
	}


	public function registerCpRoutes()
	{
		return require(__DIR__.DIRECTORY_SEPARATOR.'routes.php');
	}

	public function addTwigExtension()
	{
		return new MarketTwigExtension;
	}

	protected function defineSettings()
	{
		$settingModel = new Market_SettingsModel;

		return $settingModel->defineAttributes();
	}

	private function initMarketNav()
	{
		if(craft()->request->isCpRequest())
		{
			craft()->templates->includeCssResource('market/market-nav.css');

			craft()->templates->includeJsResource('market/market-nav.js');

			$nav = array(
				array(
					'url' => 'market/orders',
					'title' => Craft::t("Orders"),
					'selected' => (craft()->request->getSegment(2) == 'orders' ? true : false)
				),
				array(
					'url' => 'market/products',
					'title' => Craft::t("Products"),
					'selected' => (craft()->request->getSegment(2) == 'products' ? true : false)
				),
				array(
					'url' => 'market/sales',
					'title' => Craft::t("Sales"),
					'selected' => (craft()->request->getSegment(2) == 'sales' ? true : false)
				),
				array(
					'url' => 'market/discounts',
					'title' => Craft::t("Discounts"),
					'selected' => (craft()->request->getSegment(2) == 'discounts' ? true : false)
				),
				array(
					'url' => 'market/settings',
					'title' => Craft::t("Settings"),
					'selected' => (craft()->request->getSegment(2) == 'settings' ? true : false)
				),
			);

			$navJson = JsonHelper::encode($nav);

			craft()->templates->includeJs('new Craft.MarketNav('.$navJson.');');
		}
	}

}

