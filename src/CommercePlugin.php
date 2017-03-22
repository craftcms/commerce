<?php

namespace Craft;

use Commerce\Extensions\CommerceTwigExtension;
use craft\commerce\helpers\Db;
use craft\commerce\models\Settings;

require __DIR__.'/vendor/autoload.php';

/**
 * Craft Commerce Plugin for Craft CMS.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce
 * @since     1.0
 */
class CommercePlugin extends BasePlugin
{
    private $doSeed = true;

    /**
     * Initialize the plugin.
     */
    public function init()
    {
        $this->initEventHandlers();

        // If this is a CP request, register the commerce.prepCpTemplate hook
        if (Craft::$app->getRequest()->isCpRequest()) {
            $this->includeCpResources();
            Craft::$app->getView()->hook('commerce.prepCpTemplate', array($this, 'prepCpTemplate'));
        }
    }

    /**
     * Set up all event handlers.
     */
    private function initEventHandlers()
    {
        //init global event handlers
        craft()->on('i18n.onAddLocale', array(Plugin::getInstance()->getProductTypes(), 'addLocaleHandler'));

        if (!craft()->isConsole()) {
            craft()->on('users.onSaveUser', array(Plugin::getInstance()->getCustomers(), 'saveUserHandler'));
            craft()->on('userSession.onLogin', array(Plugin::getInstance()->getCustomers(), 'loginHandler'));
            craft()->on('userSession.onLogout', array(Plugin::getInstance()->getCustomers(), 'logoutHandler'));
        }
    }

    /**
     * Includes front end resources for Control Panel requests.
     */
    private function includeCpResources()
    {
        $templatesService = Craft::$app->getView();
        $templatesService->includeCssResource('commerce/commerce.css');
        $templatesService->includeJsResource('commerce/js/Commerce.js');
        $templatesService->includeJsResource('commerce/js/CommerceProductIndex.js');
        $templatesService->includeTranslations(
            'New {productType} product',
            'New product',
            'Update Order Status',
            'Message',
            'Status change message',
            'Update',
            'Cancel',
            'First Name',
            'Last Name',
            'Address Line 1',
            'Address Line 2',
            'City',
            'Zip Code',
            'Phone',
            'Alternative Phone',
            'Phone (Alt)',
            'Business Name',
            'Business Tax ID',
            'Country',
            'State',
            'Update Address',
            'New',
            'Edit',
            'Add Address',
            'Add',
            'Update',
            'No Address'
        );
    }

    /**
     * @inheritdoc
     *
     * @return string
     */
    public function getDeveloper()
    {
        return 'Pixel & Tonic';
    }

    /**
     * Commerce Developer URL.
     *
     * @return string
     */
    public function getDeveloperUrl()
    {
        return 'https://craftcommerce.com';
    }

    /**
     * Commerce Documentation URL.
     *
     * @return string
     */
    public function getDocumentationUrl()
    {
        return 'https://craftcommerce.com/docs';
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
     * Make sure requirements are met before installation.
     *
     * @return bool
     * @throws Exception
     */
    public function onBeforeInstall()
    {
        if (version_compare(craft()->getVersion(), '2.6', '<')) {
            // No way to gracefully handle this, so throw an Exception.
            throw new Exception('Craft Commerce 1.2 requires Craft CMS 2.6+ in order to run.');
        }

        if (!defined('PHP_VERSION_ID') || PHP_VERSION_ID < 50400) {
            Craft::log('Craft Commerce requires PHP 5.4+ in order to run.', LogLevel::Error);
            return false;
        }
    }

    /**
     * @inheritDoc IPlugin::onBeforeUninstall()
     *
     * @return void
     */
    public function onBeforeUninstall()
    {
        // Delete the order element index settings
        $ordersElementSettings = ElementIndexSettingsRecord::model()->findByAttributes(['type' => 'Commerce_Order']);
        if ($ordersElementSettings)
        {
            $ordersElementSettings->delete();
        }

        // Delete the order element index settings
        $productsElementSettings = ElementIndexSettingsRecord::model()->findByAttributes(['type' => 'Commerce_Product']);
        if ($productsElementSettings)
        {
            $productsElementSettings->delete();
        }
    }

    /**
     * Commerce Version.
     *
     * @return string
     */
    public function getVersion()
    {
        return '1.2.0000';
    }

    /**
     * Commerce Schema Version.
     *
     * @return string|null
     */
    public function getSchemaVersion()
    {
        return '1.2.73';
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
     * Adds the Commerce twig extensions.
     *
     * @return CommerceTwigExtension
     */
    public function addTwigExtension()
    {
        return new CommerceTwigExtension;
    }

    /**
     * Adds alerts to the control panel
     * @param $path
     * @param $fetch
     *
     * @return array|null
     */
    public function getCpAlerts($path, $fetch)
    {
        if ($path != 'commerce/settings/registration')
        {
            $licenseKeyStatus = craft()->plugins->getPluginLicenseKeyStatus('Commerce');

            if ($licenseKeyStatus == LicenseKeyStatus::Unknown)
            {
                if (!craft()->canTestEditions())
                {
                    $message = Craft::t('You haven’t entered your Commerce license key yet.');
                }
            }
            else if ($licenseKeyStatus == LicenseKeyStatus::Invalid)
            {
                $message = Craft::t('Your Commerce license key is invalid.');
            }
            else if ($licenseKeyStatus == LicenseKeyStatus::Mismatched)
            {
                $message = Craft::t('Your Commerce license key is being used on another Craft install.');
            }

            if (isset($message))
            {
                $message .= ' ';

                if (Craft::$app->getUser()->isAdmin())
                {
                    $message .= '<a class="go" href="'.UrlHelper::getUrl('commerce/settings/registration').'">'.Craft::t('Resolve').'</a>';
                }
                else
                {
                    $message .= Craft::t('Please notify one of your site’s admins.');
                }

                return [$message];
            }
        }

        return null;
    }

    /**
     * Adds custom link options to Rich Text fields.
     *
     * @return array
     */
    public function addRichTextLinkOptions()
    {
        $linkOptions = array();

        // Include a Product link option if there are any product types that have URLs
        $productSources = array();

        foreach (Plugin::getInstance()->getProductTypes()->getAllProductTypes() as $productType)
        {
            if ($productType->hasUrls)
            {
                $productSources[] = 'productType:'.$productType->id;
            }
        }

        if ($productSources)
        {
            $linkOptions[] = array(
                'optionTitle' => Craft::t('Link to a product'),
                'elementType' => 'Commerce_Product',
                'sources' => $productSources,
            );
        }

        return $linkOptions;
    }

    /**
     * Get Settings URL
     */
    public function getSettingsUrl()
    {
        return 'commerce/settings/general';
    }

    /**
     * Prepares a CP template.
     *
     * @param &$context The current template context
     */
    public function prepCpTemplate(&$context)
    {
        $context['subnav'] = array();

        if (Craft::$app->getUser()->checkPermission('commerce-manageOrders')) {
            $context['subnav']['orders'] = array('label' => Craft::t('Orders'), 'url' => 'commerce/orders');
        }

        if (Craft::$app->getUser()->checkPermission('commerce-manageProducts')) {
            $context['subnav']['products'] = array('label' => Craft::t('Products'), 'url' => 'commerce/products');
        }

        if (Craft::$app->getUser()->checkPermission('commerce-managePromotions')) {
            $context['subnav']['promotions'] = array('label' => Craft::t('Promotions'), 'url' => 'commerce/promotions');
        }

        if (Craft::$app->getUser()->isAdmin()) {
            $context['subnav']['settings'] = array('label' => Craft::t('Settings'), 'url' => 'commerce/settings');
        }
    }

    /**
     * @return array
     */
    public function registerUserPermissions()
    {
        $productTypes = Plugin::getInstance()->getProductTypes()->getAllProductTypes('id');

        $productTypePermissions = array();
        foreach ($productTypes as $id => $productType) {
            $suffix = ':' . $id;
            $productTypePermissions["commerce-manageProductType" . $suffix] = array(
                'label' => Craft::t('Manage “{type}” products', ['type' => $productType->name])
            );
        }

        return array(
            'commerce-manageProducts' => array('label' => Craft::t('Manage products'), 'nested' => $productTypePermissions),
            'commerce-manageOrders' => array('label' => Craft::t('Manage orders')),
            'commerce-managePromotions' => array('label' => Craft::t('Manage promotions')),
        );
    }

    /**
     * Define Commerce Settings.
     *
     * @return array
     */
    protected function defineSettings()
    {
        $settingModel = new Settings();

        return $settingModel->attributes;
    }

}
