<?php

namespace Craft;

require 'vendor/autoload.php';

use Market\Extensions\MarketTwigExtension;

//use Market\Market;

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
//        Market::app()["hashids"] = function ($c) {
//			$len = craft()->config->get('orderNumberLength', $this->handle);
//			$alphabet = craft()->config->get('orderNumberAlphabet', $this->handle);
//			return new \Hashids\Hashids("market",$len,$alphabet);
//		};
	}

	public function getName()
	{
		return "Market";
	}

	public function getVersion()
	{
		return "0.0.2";
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

	public function registerCpRoutes()
	{
		return require(__DIR__ . '/routes.php');
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

}

