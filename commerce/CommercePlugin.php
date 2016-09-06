<?php

namespace Craft;

use Commerce\Extensions\CommerceTwigExtension;
use Commerce\Helpers\CommerceDbHelper;

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
        if (craft()->request->isCpRequest()) {
            $this->includeCpResources();
            craft()->templates->hook('commerce.prepCpTemplate', array($this, 'prepCpTemplate'));
        }
    }

    /**
     * Set up all event handlers.
     */
    private function initEventHandlers()
    {
        //init global event handlers
        craft()->on('i18n.onAddLocale', array(craft()->commerce_productTypes, 'addLocaleHandler'));

        if (!craft()->isConsole()) {
            craft()->on('users.onSaveUser', array(craft()->commerce_customers, 'saveUserHandler'));
            craft()->on('userSession.onLogin', array(craft()->commerce_customers, 'loginHandler'));
            craft()->on('userSession.onLogout', array(craft()->commerce_customers, 'logoutHandler'));
        }
    }

    /**
     * Includes front end resources for Control Panel requests.
     */
    private function includeCpResources()
    {
        $templatesService = craft()->templates;
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
            'Update'
        );
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
            if ($pluginInfo['version'] != '0.8.09') {
                throw new Exception('Market plugin must be upgraded to 0.8.09 before installing Commerce');
            }

            if ($pluginInfo['version'] == '0.8.09') {
                CommerceDbHelper::beginStackedTransaction();
                try {
                    $this->doSeed = false;

                    $migrations = array(
                        'm150916_010101_Commerce_Rename',
                        'm150917_010101_Commerce_DropEmailTypeColumn',
                        'm150917_010102_Commerce_RenameCodeToHandletaxCatColumn',
                        'm150918_010101_Commerce_AddProductTypeLocales',
                        'm150918_010102_Commerce_RemoveNonLocaleBasedUrlFormat',
                        'm150919_010101_Commerce_AddHasDimensionsToProductType',
                        'm151004_142113_commerce_PaymentMethods_name_unique',
                        'm151018_010101_Commerce_DiscountCodeNull',
                        'm151025_010101_Commerce_AddHandleToShippingMethod',
                        'm151027_010101_Commerce_NewVariantUI',
                        'm151027_010102_Commerce_ProductDateNames',
                        'm151102_010101_Commerce_PaymentTypeInMethodNotSettings',
                        'm151103_010101_Commerce_DefaultVariant',
                        'm151109_010101_Commerce_AddCompanyNumberToAddress',
                        'm151110_010101_Commerce_RenameCompanyToAddress',
                        'm151111_010101_Commerce_ShowVariantTitleField',
                        'm151112_010101_Commerce_AutoSkuFormat',
                        'm151109_010102_Commerce_AddOptionsToLineItems',
                        'm151117_010101_Commerce_TaxIncluded',
                        'm151124_010101_Commerce_AddressManagement',
                        'm151127_010101_Commerce_TaxRateTaxableOptions',
                        'm151210_010101_Commerce_FixMissingLineItemDimensionData',
                        'm160215_010101_Commerce_ConsistentDecimalType',
                        'm160226_010101_Commerce_OrderStatusSortOrder',
                        'm160226_010102_Commerce_isCompleted',
                        'm160227_010101_Commerce_OrderAdjustmentIncludedFlag',
                        'm160229_010101_Commerce_ShippingZone',
                        'm160229_010104_Commerce_SoftDeleteAndReorderPaymentMethod',
                        'm160401_010101_Commerce_KeepAllTransactions',
                        'm160405_010101_Commerce_FixDefaultVariantId'.
                        'm160406_010101_Commerce_RemoveUnusedAuthorId',
                        'm160425_010101_Commerce_DeleteCountriesAndStates',
                        'm160606_010101_Commerce_PerEmailLimitOnDiscount',
                        'm160510_010101_Commerce_EmailRecipientType',
                        'm160510_010101_Commerce_Currencies'
                    );

                    foreach ($migrations as $migrationClass) {
                        $migration = craft()->migrations->instantiateMigration($migrationClass, $this);
                        if (!$migration->up()) {
                            Craft::log("Market to Commerce Upgrade Error. Could not run: " . $migrationClass, LogLevel::Error);
                            throw new Exception('Market to Commerce Upgrade Error.');
                        }
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
        return 'Commerce';
    }

    /**
     * The plugin description.
     *
     * @return string|null
     */
    public function getDescription()
    {
        return 'An amazingly powerful and flexible e-commerce platform for Craft CMS.';
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
     * Make sure requirements are met before installation.
     *
     * @return bool
     * @throws Exception
     */
    public function onBeforeInstall()
    {
        if (version_compare(craft()->getVersion(), '2.6', '<')) {
            // No way to gracefully handle this, so throw an Exception.
            throw new Exception('Craft Commerce 1.1 requires Craft CMS 2.6+ in order to run.');
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
        return '1.2.35';
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
     * Adds alerts to the CP.
     *
     * @param string|null $path
     * @param bool        $fetch
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

                if (craft()->userSession->isAdmin())
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

        foreach (craft()->commerce_productTypes->getAllProductTypes() as $productType)
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

        if (craft()->userSession->checkPermission('commerce-manageOrders')) {
            $context['subnav']['orders'] = array('label' => Craft::t('Orders'), 'url' => 'commerce/orders');
        }

        if (craft()->userSession->checkPermission('commerce-manageProducts')) {
            $context['subnav']['products'] = array('label' => Craft::t('Products'), 'url' => 'commerce/products');
        }

        if (craft()->userSession->checkPermission('commerce-managePromotions')) {
            $context['subnav']['promotions'] = array('label' => Craft::t('Promotions'), 'url' => 'commerce/promotions');
        }

        if (craft()->userSession->isAdmin()) {
            $context['subnav']['settings'] = array('label' => Craft::t('Settings'), 'url' => 'commerce/settings');
        }
    }

    /**
     * @return array
     */
    public function registerUserPermissions()
    {
        $productTypes = craft()->commerce_productTypes->getAllProductTypes('id');

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
        $settingModel = new Commerce_SettingsModel;

        return $settingModel->defineAttributes();
    }

}
