<?php

namespace Craft;

use Commerce\Extensions\CommerceTwigExtension;
use Commerce\Helpers\CommerceDbHelper;

require 'vendor/autoload.php';

class CommercePlugin extends BasePlugin
{
	public $handle = 'commerce';
	private $doSeed = true;

	/**
	 * Initialize plugin.
	 */
	public function init ()
	{
		if (!defined('DOMPDF_ENABLE_AUTOLOAD'))
		{
			// disable DOMPDF's internal autoloader since we are using Composer
			define('DOMPDF_ENABLE_AUTOLOAD', false);
			// include DOMPDF's configuration
			require_once __DIR__.'/vendor/dompdf/dompdf/dompdf_config.inc.php';
		}

		$this->initEventHandlers();
	}

	/**
	 * Set up all event handlers.
	 */
	private function initEventHandlers ()
	{
		//init global event handlers
		craft()->on('commerce_orderHistory.onStatusChange', [craft()->commerce_orderStatus, 'statusChangeHandler']);
		craft()->on('commerce_order.onOrderComplete', [craft()->commerce_discount, 'orderCompleteHandler']);
		craft()->on('commerce_order.onOrderComplete', [craft()->commerce_variant, 'orderCompleteHandler']);
		craft()->on('i18n.onAddLocale', [craft()->commerce_productType, 'addLocaleHandler']);
		if (!craft()->isConsole())
		{
			craft()->on('userSession.onLogin', [craft()->commerce_customer, 'loginHandler']);
		}
	}

	/**
	 * Handle rename.
	 */
	public function createTables ()
	{
		$pluginInfo = craft()->db->createCommand()
			->select('id, version')
			->from('plugins')
			->where("class = 'Market'")
			->queryRow();

		if (!$pluginInfo)
		{
			parent::createTables();
		}
		else
		{
			if ($pluginInfo['version'] != '0.7.99')
			{
				throw new Exception('Market plugin must be upgraded to 0.7.99 before installing Commerce');
			}

			if ($pluginInfo['version'] == '0.7.99')
			{
				CommerceDbHelper::beginStackedTransaction();
				try
				{
					$this->doSeed = false;
					MigrationHelper::renameTable('market_addresses', 'commerce_addresses');
					MigrationHelper::renameTable('market_charges', 'commerce_charges');
					MigrationHelper::renameTable('market_countries', 'commerce_countries');
					MigrationHelper::renameTable('market_customer_discountuses', 'commerce_customer_discountuses');
					MigrationHelper::renameTable('market_customers', 'commerce_customers');
					MigrationHelper::renameTable('market_discount_products', 'commerce_discount_products');
					MigrationHelper::renameTable('market_discount_producttypes', 'commerce_discount_producttypes');
					MigrationHelper::renameTable('market_discount_usergroups', 'commerce_discount_usergroups');
					MigrationHelper::renameTable('market_discounts', 'commerce_discounts');
					MigrationHelper::renameTable('market_emails', 'commerce_emails');
					MigrationHelper::renameTable('market_lineitems', 'commerce_lineitems');
					MigrationHelper::renameTable('market_orderadjustments', 'commerce_orderadjustments');
					MigrationHelper::renameTable('market_orderhistories', 'commerce_orderhistories');
					MigrationHelper::renameTable('market_orders', 'commerce_orders');
					MigrationHelper::renameTable('market_ordersettings', 'commerce_ordersettings');
					MigrationHelper::renameTable('market_orderstatus_emails', 'commerce_orderstatus_emails');
					MigrationHelper::renameTable('market_orderstatuses', 'commerce_orderstatuses');
					MigrationHelper::renameTable('market_paymentmethods', 'commerce_paymentmethods');
					MigrationHelper::renameTable('market_products', 'commerce_products');
					MigrationHelper::renameTable('market_producttypes', 'commerce_producttypes');
					MigrationHelper::renameTable('market_purchasables', 'commerce_purchasables');
					MigrationHelper::renameTable('market_sale_products', 'commerce_sale_products');
					MigrationHelper::renameTable('market_sale_producttypes', 'commerce_sale_producttypes');
					MigrationHelper::renameTable('market_sale_usergroups', 'commerce_sale_usergroups');
					MigrationHelper::renameTable('market_sales', 'commerce_sales');
					MigrationHelper::renameTable('market_shippingmethods', 'commerce_shippingmethods');
					MigrationHelper::renameTable('market_shippingrules', 'commerce_shippingrules');
					MigrationHelper::renameTable('market_states', 'commerce_states');
					MigrationHelper::renameTable('market_taxcategories', 'commerce_taxcategories');
					MigrationHelper::renameTable('market_taxrates', 'commerce_taxrates');
					MigrationHelper::renameTable('market_taxzone_countries', 'commerce_taxzone_countries');
					MigrationHelper::renameTable('market_taxzone_states', 'commerce_taxzone_states');
					MigrationHelper::renameTable('market_taxzones', 'commerce_taxzones');
					MigrationHelper::renameTable('market_transactions', 'commerce_transactions');
					MigrationHelper::renameTable('market_variants', 'commerce_variants');

					craft()->db->createCommand()->delete('plugins', "class = 'Market'");

					$migrations = [
						'm150917_010101_Commerce_DropEmailTypeColumn',
						'm150917_010102_Commerce_RenameCodeToHandletaxCatColumn',
						'm150918_010101_Commerce_AddProductTypeLocales',
						'm150918_010102_Commerce_RemoveNonLocaleBasedUrlFormat',
						'm150919_010101_Commerce_AddHasDimensionsToProductType'
					];

					foreach ($migrations as $migrationClass)
					{
						$migration = craft()->migrations->instantiateMigration($migrationClass, $this);
						$migration->up();
					}

					CommerceDbHelper::commitStackedTransaction();
				}
				catch (Exception $e)
				{
					CommerceDbHelper::rollbackStackedTransaction();
				}
			}
		}
	}

	/**
	 * The plugin name.
	 *
	 * @return string
	 */
	public function getName ()
	{
		return "Commerce";
	}

	/**
	 * @inheritdoc
	 *
	 * @return string
	 */
	public function getDeveloper ()
	{
		return "Pixel & Tonic";
	}

	/**
	 * Commerce Developer URL.
	 *
	 * @return string
	 */
	public function getDeveloperUrl ()
	{
		return "http://buildwithcraft.com/commerce";
	}

	/**
	 * Commerce has a control panel section.
	 *
	 * @return bool
	 */
	public function hasCpSection ()
	{
		return true;
	}

	/**
	 * After install, run seeders and optional test data.
	 *
	 */
	public function onAfterInstall ()
	{
		if ($this->doSeed)
		{
			craft()->commerce_seed->afterInstall();
		}
	}

	/**
	 * Commerce Commerce Version.
	 *
	 * @return string
	 */
	public function getVersion ()
	{
		return '0.8.11';
	}

	/**
	 * A&M Command Palette data. Enables shortcuts to different areas of the
	 * control panel.
	 *
	 * @return mixed
	 */
	public function addCommands ()
	{
		return require(__DIR__.'/etc/commands.php');
	}

	/**
	 * Control Panel routes.
	 *
	 * @return mixed
	 */
	public function registerCpRoutes ()
	{
		return require(__DIR__.'/etc/routes.php');
	}

	/**
	 * Adds the Commerce twig extensions
	 *
	 * @return CommerceTwigExtension
	 */
	public function addTwigExtension ()
	{
		return new CommerceTwigExtension;
	}

	/**
	 * Get Settings URL
	 */
	public function getSettingsUrl ()
	{
		return 'commerce/settings';
	}

	/**
	 * Define Commerce Settings.
	 *
	 * @return array
	 */
	protected function defineSettings ()
	{
		$settingModel = new Commerce_SettingsModel;

		return $settingModel->defineAttributes();
	}

}
