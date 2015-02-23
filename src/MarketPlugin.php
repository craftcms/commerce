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
		return require(__DIR__ . '/routes.php');
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

