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
        //init global event handler
        craft()->on('market_orderHistory.onStatusChange',
			[
				craft()->market_orderStatus, 'statusChangeHandler'
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

}

