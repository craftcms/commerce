<?php

namespace Craft;

use Commerce\Extensions\CommerceTwigExtension;
use Commerce\Helpers\CommerceDbHelper;

require 'vendor/autoload.php';

/**
 * Craft Commerce Plugin for Craft CMS.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce
 * @since     1.0
 */
class CommercePlugin extends BasePlugin
{
    public $handle = 'commerce';
    private $doSeed = true;

    /**
     * Initialize plugin.
     */
    public function init()
    {
        if (!defined('DOMPDF_ENABLE_AUTOLOAD')) {
            // disable DOMPDF's internal autoloader since we are using Composer
            define('DOMPDF_ENABLE_AUTOLOAD', false);
            // include DOMPDF's configuration
            require_once __DIR__ . '/vendor/dompdf/dompdf/dompdf_config.inc.php';
        }

        $this->initEventHandlers();

        // If this is a CP request, register the commerce.prepCpTemplate hook
        if (craft()->request->isCpRequest()) {
            craft()->templates->hook('commerce.prepCpTemplate', [$this, 'prepCpTemplate']);
        }
    }

    /**
     * Set up all event handlers.
     */
    private function initEventHandlers()
    {
        //init global event handlers
        craft()->on('commerce_orderHistories.onStatusChange', [craft()->commerce_orderStatuses, 'statusChangeHandler']);
        craft()->on('commerce_orders.onOrderComplete', [craft()->commerce_discounts, 'orderCompleteHandler']);
        craft()->on('commerce_orders.onOrderComplete', [craft()->commerce_variants, 'orderCompleteHandler']);
        craft()->on('i18n.onAddLocale', [craft()->commerce_productTypes, 'addLocaleHandler']);
        if (!craft()->isConsole()) {
            craft()->on('userSession.onLogin', [craft()->commerce_customers, 'loginHandler']);
        }
    }

    /**
     * Handle rename.
     */
    public function createTables()
    {
        $pluginInfo = craft()->db->createCommand()
            ->select('id, version')
            ->from('plugins')
            ->where("class = 'Market'")
            ->queryRow();

        if (!$pluginInfo) {
            parent::createTables();
        } else {
            if ($pluginInfo['version'] != '0.8.05') {
                throw new Exception('Market plugin must be upgraded to 0.8.05 before installing Commerce');
            }

            if ($pluginInfo['version'] == '0.8.05') {
                CommerceDbHelper::beginStackedTransaction();
                try {
                    $this->doSeed = false;

                    $migrations = [
                        'm150916_010101_Commerce_Rename',
                        'm150917_010101_Commerce_DropEmailTypeColumn',
                        'm150917_010102_Commerce_RenameCodeToHandletaxCatColumn',
                        'm150918_010101_Commerce_AddProductTypeLocales',
                        'm150918_010102_Commerce_RemoveNonLocaleBasedUrlFormat',
                        'm150919_010101_Commerce_AddHasDimensionsToProductType',
                        'm151004_142113_Commerce_PaymentMethods_Name_Unique'
                    ];

                    foreach ($migrations as $migrationClass) {
                        $migration = craft()->migrations->instantiateMigration($migrationClass, $this);
                        $migration->up();
                    }

                    CommerceDbHelper::commitStackedTransaction();
                } catch (Exception $e) {
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
    public function getName()
    {
        return "Commerce";
    }

    /**
     * @inheritdoc
     *
     * @return string
     */
    public function getDeveloper()
    {
        return "Pixel & Tonic";
    }

    /**
     * Commerce Developer URL.
     *
     * @return string
     */
    public function getDeveloperUrl()
    {
        return "http://craftcommerce.com";
    }

    /**
     * Commerce has a control panel section.
     *
     * @return bool
     */
    public function hasCpSection()
    {
        return true;
    }

    /**
     * After install, run seeders and optional test data.
     *
     */
    public function onAfterInstall()
    {
        if ($this->doSeed) {
            craft()->commerce_seed->afterInstall();
        }
    }

    /**
     * Commerce Commerce Version.
     *
     * @return string
     */
    public function getVersion()
    {
        return '0.8.23';
    }

    /**
     * A&M Command Palette data. Enables shortcuts to different areas of the
     * control panel.
     *
     * @return mixed
     */
    public function addCommands()
    {
        return require(__DIR__ . '/etc/commands.php');
    }

    /**
     * Control Panel routes.
     *
     * @return mixed
     */
    public function registerCpRoutes()
    {
        return require(__DIR__ . '/etc/routes.php');
    }

    /**
     * @return array
     */
    public function registerCommerceGatewayAdapters()
    {
        return require(__DIR__ . '/etc/gatewayAdapters.php');
    }

    /**
     * Adds the Commerce twig extensions
     *
     * @return CommerceTwigExtension
     */
    public function addTwigExtension()
    {
        return new CommerceTwigExtension;
    }

    /**
     * Get Settings URL
     */
    public function getSettingsUrl()
    {
        return 'commerce/settings/global';
    }

    /**
     * Prepares a CP template.
     *
     * @param &$context The current template context
     */
    public function prepCpTemplate(&$context)
    {
        $context['subnav'] = [
            'orders' => ['label' => Craft::t('Orders'), 'url' => 'commerce/orders'],
            'products' => ['label' => Craft::t('Products'), 'url' => 'commerce/products'],
            'promotions' => ['label' => Craft::t('Promotions'), 'url' => 'commerce/promotions'],
        ];

        if (craft()->userSession->isAdmin()) {
            $context['subnav']['settings'] = ['icon' => 'settings', 'label' => Craft::t('Settings'), 'url' => 'commerce/settings'];
        }

        craft()->templates->includeCssResource('commerce/commerce.css');
    }

    /**
     * Define Commerce Settings.
     *
     * @return array
     */
    protected function defineSettings()
    {
        $settingModel = new Commerce_SettingsModel;

        return $settingModel->defineAttributes();
    }

}
