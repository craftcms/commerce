<?php
namespace craft\commerce;

use Craft;
use craft\commerce\elements\Product;
use craft\commerce\models\Settings;
use craft\commerce\plugin\Routes;
use craft\commerce\plugin\Services as CommerceServices;
use craft\commerce\variables\Commerce;
use craft\commerce\web\twig\Extension;
use craft\commerce\widgets\Orders;
use craft\commerce\widgets\Revenue;
use craft\enums\LicenseKeyStatus;
use craft\events\DefineComponentsEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterCpAlertsEvent;
use craft\events\RegisterRichTextLinkOptionsEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\fields\RichText;
use craft\helpers\Cp as CpHelper;
use craft\helpers\UrlHelper;
use craft\services\Dashboard;
use craft\services\Sites;
use craft\elements\User as UserElement;
use craft\services\UserPermissions;
use craft\web\twig\variables\CraftVariable;
use yii\base\Event;
use yii\base\Exception;
use yii\web\User;

class Plugin extends \craft\base\Plugin
{
    // Public Properties
    // =========================================================================

    public $schemaVersion = '2.0.3';

    // Traits
    // =========================================================================

    use CommerceServices;
    use Routes;

    // Constants
    // =========================================================================

    /**
     * @event \yii\base\Event The event that is triggered after the plugin has been initialized
     */
    const EVENT_AFTER_INIT = 'afterInit';

    // Public Methods
    // =========================================================================

    public function init()
    {
        parent::init();

        $this->_setPluginComponents();
        $this->_registerCpRoutes();
        $this->_addTwigExtensions();
        $this->_registerFieldTypes();
        $this->_registerRichTextLinks();
        $this->_registerPermissions();
        $this->_registerSessionEventListeners();
        $this->_registerCpAlerts();
        $this->_registerWidgets();

        // Fire an 'afterInit' event
        $this->trigger(Plugin::EVENT_AFTER_INIT);
    }

    /**
     * Pre-install checks
     *
     * @return bool
     * @throws Exception
     */
    public function beforeInstall(): bool
    {
        if (version_compare(Craft::$app->getInfo()->version, '3.0', '<')) {
            throw new Exception('Craft Commerce 2 requires Craft CMS 3+ in order to run.');
        }

        if (!defined('PHP_VERSION_ID') || PHP_VERSION_ID < 70000) {
            Craft::error('Craft Commerce requires PHP 7.0+ in order to run.');

            return false;
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function getCpNavItem(): array
    {
        $iconPath = Plugin::getInstance()->getBasePath().DIRECTORY_SEPARATOR.'icon-mask.svg';

        if (is_file($iconPath)) {
            $iconSvg = file_get_contents($iconPath);
        } else {
            $iconSvg = false;
        }

        $navItems = [
            'label' => Craft::t('commerce','Commerce'),
            'url' => Plugin::getInstance()->id,
            'iconSvg' => $iconSvg
        ];

        if (Craft::$app->getUser()->checkPermission('commerce-manageOrders')) {
            $navItems['subnav']['orders'] = [
                'label' => Craft::t('commerce', 'Orders'),
                'url' => 'commerce/orders'
            ];
        }

        if (count($this->getProductTypes()->getEditableProductTypes()) > 0) {
            if (Craft::$app->getUser()->checkPermission('commerce-manageProducts')) {
                $navItems['subnav']['products'] = [
                    'label' => Craft::t('commerce', 'Products'),
                    'url' => 'commerce/products'
                ];
            }
        }

        if (Craft::$app->getUser()->checkPermission('commerce-managePromotions')) {
            $navItems['subnav']['promotions'] = [
                'label' => Craft::t('commerce', 'Promotions'),
                'url' => 'commerce/promotions'
            ];
        }

        if (Craft::$app->user->identity->admin) {
            $navItems['subnav']['settings'] = [
                'label' => Craft::t('commerce', 'Settings'),
                'url' => 'commerce/settings'
            ];
        }

        return $navItems;
    }

    // Protected Methods
    // =========================================================================

    protected function createSettingsModel()
    {
        return new Settings();
    }

    // Private Methods
    // =========================================================================

    /**
     * Add the twig extension
     */
    private function _addTwigExtensions()
    {
        Event::on(CraftVariable::class, CraftVariable::EVENT_DEFINE_COMPONENTS, function (DefineComponentsEvent $event){
                $event->components['commerce'] = Commerce::class;
        });

        Craft::$app->view->twig->addExtension(new Extension);
    }

    /**
     * Register links to product in the rich text field
     */
    private function _registerRichTextLinks()
    {
        Event::on(RichText::class, RichText::EVENT_REGISTER_LINK_OPTIONS, function(RegisterRichTextLinkOptionsEvent $event) {
            // Include a Product link option if there are any product types that have URLs
            $productSources = [];

            foreach (Plugin::getInstance()->getProductTypes()->getAllProductTypes() as $productType) {
                if ($productType->hasUrls) {
                    $productSources[] = 'productType:'.$productType->id;
                }
            }

            if ($productSources) {
                $event->linkOptions[] = [
                    'optionTitle' => Craft::t('commerce', 'Link to a product'),
                    'elementType' => Product::class,
                    'sources' => $productSources
                ];
            }
        });
    }

    /**
     * Register commerce permissions
     */
    private function _registerPermissions()
    {
        Event::on(UserPermissions::class, UserPermissions::EVENT_REGISTER_PERMISSIONS, function(RegisterUserPermissionsEvent $event) {

            $productTypes = Plugin::getInstance()->getProductTypes()->getAllProductTypes('id');

            $productTypePermissions = [];
            foreach ($productTypes as $id => $productType) {
                $suffix = ':'.$id;
                $productTypePermissions["commerce-manageProductType".$suffix] = ['label' => Craft::t('commerce', 'Manage “{type}” products', ['type' => $productType->name])];
            }

            $event->permissions[] = [
                'commerce-manageProducts' => ['label' => Craft::t('commerce', 'Manage products'), 'nested' => $productTypePermissions],
                'commerce-manageOrders' => ['label' => Craft::t('commerce', 'Manage orders')],
                'commerce-managePromotions' => ['label' => Craft::t('commerce', 'Manage promotions')],
            ];
        });
    }

    /**
     *
     */
    private function _registerSessionEventListeners()
    {
        Event::on(Sites::class, Sites::EVENT_AFTER_SAVE_SITE, [$this->getProductTypes(), 'addSiteHandler']);

        if (!Craft::$app->getRequest()->getIsConsoleRequest()) {
            Event::on(UserElement::class, UserElement::EVENT_AFTER_SAVE, [$this->getCustomers(), 'saveUserHandler']);
            Event::on(User::class, User::EVENT_AFTER_LOGIN, [$this->getCustomers(), 'loginHandler']);
            Event::on(User::class, User::EVENT_AFTER_LOGOUT, [$this->getCustomers(), 'logoutHandler']);
        }
    }

    /**
     *
     */
    private function _registerCpAlerts()
    {
        Event::on(CpHelper::class, CpHelper::EVENT_REGISTER_ALERTS, function(RegisterCpAlertsEvent $event) {

            if (Craft::$app->getRequest()->getFullPath() != 'commerce/settings/registration') {

                $message = null;

                $licenseKeyStatus = Craft::$app->getPlugins()->getPluginLicenseKeyStatus('Commerce');

                $message = null;

                if ($licenseKeyStatus == LicenseKeyStatus::Unknown) {
                    if (!Craft::$app->canTestEditions) {
                        $message = Craft::t('commerce', 'You haven’t entered your Commerce license key yet.');
                    }
                } else if ($licenseKeyStatus == LicenseKeyStatus::Invalid) {
                    $message = Craft::t('commerce', 'Your Commerce license key is invalid.');
                } else if ($licenseKeyStatus == LicenseKeyStatus::Mismatched) {
                    $message = Craft::t('commerce', 'Your Commerce license key is being used on another Craft install.');
                }

                if (null !== $message) {
                    $message .= ' ';

                    if (Craft::$app->getUser()->getIsAdmin()) {
                        $message .= '<a class="go" href="'.UrlHelper::cpUrl('commerce/settings/registration').'">'.Craft::t('commerce', 'Resolve').'</a>';
                    } else {
                        $message .= Craft::t('commerce', 'Please notify one of your site’s admins.');
                    }

                    $event->alerts[] = $message;
                }
            }
        });
    }

    private function _registerFieldTypes()
    {
//        Event::on(Fields::className(),
//            Fields::EVENT_REGISTER_FIELD_TYPES,
//            function (RegisterComponentTypesEvent $event) {
//                $event->types[] = ManField::class;
//            }
//        );
    }

    /**
     * Register Commerce’s widgets.
     */
    private function _registerWidgets()
    {
        Event::on(Dashboard::class, Dashboard::EVENT_REGISTER_WIDGET_TYPES, function(RegisterComponentTypesEvent $event) {
            $event->types[] = Orders::class;
            $event->types[] = Revenue::class;
        });
    }
}
