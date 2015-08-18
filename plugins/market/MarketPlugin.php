<?php

namespace Craft;

use Market\Extensions\MarketTwigExtension;

require 'vendor/autoload.php';

// disable DOMPDF's internal autoloader since we are using Composer
define('DOMPDF_ENABLE_AUTOLOAD', false);

// include DOMPDF's configuration
require_once __DIR__.'/vendor/dompdf/dompdf/dompdf_config.inc.php';

class MarketPlugin extends BasePlugin
{
    public $handle = 'market';

    /**
     * Initialize plugin.
     */
    public function init()
    {
        $this->initMarketNav();
        $this->initEventHandlers();
    }

    /**
     * Temporary nav until 2.5 is released.
     */
    private function initMarketNav()
    {
        if (craft()->request->isCpRequest()) {
            craft()->templates->includeCssResource('market/market-nav.css');

            craft()->templates->includeJsResource('market/market-nav.js');

            $nav = [
                [
                    'url'      => 'market/orders',
                    'title'    => Craft::t("Orders"),
                    'selected' => (craft()->request->getSegment(2) == 'orders' ? true : false)
                ],
                [
                    'url'      => 'market/products',
                    'title'    => Craft::t("Products"),
                    'selected' => (craft()->request->getSegment(2) == 'products' ? true : false)
                ],
                [
                    'url'      => 'market/promotions',
                    'title'    => Craft::t("Promotions"),
                    'selected' => (craft()->request->getSegment(2) == 'promotions' ? true : false)
                ],
                [
                    'url'      => 'market/customers',
                    'title'    => Craft::t("Customers"),
                    'selected' => (craft()->request->getSegment(2) == 'customers' ? true : false)
                ],
                [
                    'url'      => 'market/settings',
                    'title'    => Craft::t("Settings"),
                    'selected' => (craft()->request->getSegment(2) == 'settings' ? true : false)
                ]
            ];

            $navJson = JsonHelper::encode($nav);

            craft()->templates->includeJs('new Craft.MarketNav(' . $navJson . ');');
        }
    }

    /**
     * Set up all event handlers.
     */
    private function initEventHandlers()
    {
        //init global event handlers
        craft()->on('market_orderHistory.onStatusChange',
            [
                craft()->market_orderStatus,
                'statusChangeHandler'
            ]
        );

        craft()->on('market_order.onOrderComplete',
            [
                craft()->market_discount,
                'orderCompleteHandler'
            ]
        );

        craft()->on('market_order.onOrderComplete',
            [
                craft()->market_variant,
                'orderCompleteHandler'
            ]
        );
    }

    /**
     * The plugin name.
     *
     * @return string
     */
    public function getName()
    {
        return "Market";
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
     * Market Developer URL.
     *
     * @return string
     */
    public function getDeveloperUrl()
    {
        return "http://buildwithcraft.com/commerce";
    }

    /**
     * Market has a control panel section.
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
        craft()->market_seed->afterInstall();
    }

    /**
     * Market Commerce Version.
     *
     * @return string
     */
    public function getVersion()
    {
        return '0.6.85';
    }

    /**
     * A&M Command Palette data. Enables shortcuts to different areas of the
     * control panel.
     *
     * @return mixed
     */
    public function addCommands()
    {
        return require(__DIR__ . DIRECTORY_SEPARATOR . 'commands.php');
    }

    /**
     * Control Panel routes.
     *
     * @return mixed
     */
    public function registerCpRoutes()
    {
        return require(__DIR__ . DIRECTORY_SEPARATOR . 'routes.php');
    }

    /**
     * Adds the Market twig extensions
     *
     * @return MarketTwigExtension
     */
    public function addTwigExtension()
    {
        return new MarketTwigExtension;
    }

    /**
     * Define Market Settings.
     *
     * @return array
     */
    protected function defineSettings()
    {
        $settingModel = new Market_SettingsModel;

        return $settingModel->defineAttributes();
    }

}
