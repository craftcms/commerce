<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce;

use Craft;
use craft\base\Plugin as BasePlugin;
use craft\commerce\base\Purchasable;
use craft\commerce\db\Table;
use craft\commerce\elements\Donation;
use craft\commerce\elements\Order;
use craft\commerce\elements\Product;
use craft\commerce\elements\Subscription;
use craft\commerce\elements\Variant;
use craft\commerce\events\EmailEvent;
use craft\commerce\exports\LineItemExport;
use craft\commerce\exports\OrderExport;
use craft\commerce\fieldlayoutelements\ProductTitleField;
use craft\commerce\fieldlayoutelements\VariantsField;
use craft\commerce\fieldlayoutelements\VariantTitleField;
use craft\commerce\fields\Products;
use craft\commerce\fields\Variants;
use craft\commerce\gql\interfaces\elements\Product as GqlProductInterface;
use craft\commerce\gql\interfaces\elements\Variant as GqlVariantInterface;
use craft\commerce\gql\queries\Product as GqlProductQueries;
use craft\commerce\gql\queries\Variant as GqlVariantQueries;
use craft\commerce\helpers\ProjectConfigData;
use craft\commerce\migrations\Install;
use craft\commerce\models\Settings;
use craft\commerce\plugin\Routes;
use craft\commerce\plugin\Services as CommerceServices;
use craft\commerce\plugin\Variables;
use craft\commerce\services\Emails;
use craft\commerce\services\Gateways;
use craft\commerce\services\LineItemStatuses;
use craft\commerce\services\Orders as OrdersService;
use craft\commerce\services\OrderStatuses;
use craft\commerce\services\Pdfs;
use craft\commerce\services\ProductTypes;
use craft\commerce\services\Subscriptions;
use craft\commerce\web\twig\CraftVariableBehavior;
use craft\commerce\web\twig\Extension;
use craft\commerce\widgets\AverageOrderTotal;
use craft\commerce\widgets\NewCustomers;
use craft\commerce\widgets\Orders;
use craft\commerce\widgets\RepeatCustomers;
use craft\commerce\widgets\TopCustomers;
use craft\commerce\widgets\TopProducts;
use craft\commerce\widgets\TopProductTypes;
use craft\commerce\widgets\TopPurchasables;
use craft\commerce\widgets\TotalOrders;
use craft\commerce\widgets\TotalOrdersByCountry;
use craft\commerce\widgets\TotalRevenue;
use craft\console\Application as ConsoleApplication;
use craft\console\Controller as ConsoleController;
use craft\console\controllers\ResaveController;
use craft\elements\User as UserElement;
use craft\events\DefineConsoleActionsEvent;
use craft\events\DefineFieldLayoutFieldsEvent;
use craft\events\DeleteSiteEvent;
use craft\events\RebuildConfigEvent;
use craft\events\RegisterCacheOptionsEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterElementExportersEvent;
use craft\events\RegisterGqlEagerLoadableFields;
use craft\events\RegisterGqlQueriesEvent;
use craft\events\RegisterGqlSchemaComponentsEvent;
use craft\events\RegisterGqlTypesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\fixfks\controllers\RestoreController;
use craft\gql\ElementQueryConditionBuilder;
use craft\helpers\Console;
use craft\helpers\FileHelper;
use craft\helpers\UrlHelper;
use craft\models\FieldLayout;
use craft\redactor\events\RegisterLinkOptionsEvent;
use craft\redactor\Field as RedactorField;
use craft\services\Dashboard;
use craft\services\Elements;
use craft\services\Fields;
use craft\services\Gc;
use craft\services\Gql;
use craft\services\GraphQl;
use craft\services\ProjectConfig;
use craft\services\Sites;
use craft\services\UserPermissions;
use craft\utilities\ClearCaches;
use craft\web\twig\variables\CraftVariable;
use yii\base\Event;
use yii\base\Exception;
use yii\web\User;

/**
 * @property array $cpNavItem the control panel navigation menu
 * @property Settings $settings
 * @property mixed $settingsResponse the settings page response
 * @method Settings getSettings()
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Plugin extends BasePlugin
{
    // Edition constants
    const EDITION_LITE = 'lite';
    const EDITION_PRO = 'pro';

    public static function editions(): array
    {
        return [
            self::EDITION_LITE,
            self::EDITION_PRO,
        ];
    }

    /**
     * @param $message
     * @param array $params
     * @param null $language
     * @return string
     * @see Craft::t()
     *
     * @since 2.2.0
     * @deprecated in 3.2.4
     */
    public static function t($message, $params = [], $language = null)
    {
        return Craft::t('commerce', $message, $params, $language);
    }

    /**
     * @inheritDoc
     */
    public $schemaVersion = '3.4.13';

    /**
     * @inheritdoc
     */
    public $hasCpSettings = true;

    /**
     * @inheritdoc
     */
    public $hasCpSection = true;

    /**
     * @inheritdoc
     */
    public $minVersionRequired = '2.2.18';

    use CommerceServices;
    use Variables;
    use Routes;

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        $this->_setPluginComponents();
        $this->_addTwigExtensions();
        $this->_registerFieldTypes();
        $this->_registerPermissions();
        $this->_registerCraftEventListeners();
        $this->_registerProjectConfigEventListeners();
        $this->_registerVariables();
        $this->_registerForeignKeysRestore();
        $this->_registerPoweredByHeader();
        $this->_registerElementTypes();
        $this->_registerGqlInterfaces();
        $this->_registerGqlQueries();
        $this->_registerGqlComponents();
        $this->_registerGqlEagerLoadableFields();
        $this->_registerCacheTypes();
        $this->_registerGarbageCollection();

        $request = Craft::$app->getRequest();

        if ($request->getIsConsoleRequest()) {
            $this->_defineResaveCommand();
        } elseif ($request->getIsCpRequest()) {
            $this->_registerCpRoutes();
            $this->_registerWidgets();
            $this->_registerElementExports();
            $this->_defineFieldLayoutElements();
            $this->_registerTemplateHooks();
            $this->_registerRedactorLinkOptions();
        } else {
            $this->_registerSiteRoutes();
        }

        Craft::setAlias('@commerceLib', Craft::getAlias('@craft/commerce/../lib'));
    }

    /**
     * @inheritdoc
     */
    public function beforeInstall(): bool
    {
        // Check version before installing
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
    public function getSettingsResponse()
    {
        return Craft::$app->getResponse()->redirect(UrlHelper::cpUrl('commerce/settings/general'));
    }

    /**
     * @inheritdoc
     */
    public function getCpNavItem(): array
    {
        $ret = parent::getCpNavItem();

        $ret['label'] = Craft::t('commerce', 'Commerce');

        if (Craft::$app->getUser()->checkPermission('commerce-manageOrders')) {
            $ret['subnav']['orders'] = [
                'label' => Craft::t('commerce', 'Orders'),
                'url' => 'commerce/orders',
            ];
        }

        $hasEditableProductTypes = !empty($this->getProductTypes()->getEditableProductTypes());
        if ($hasEditableProductTypes && Craft::$app->getUser()->checkPermission('commerce-manageProducts')) {
            $ret['subnav']['products'] = [
                'label' => Craft::t('commerce', 'Products'),
                'url' => 'commerce/products',
            ];
        }

        if (Craft::$app->getUser()->checkPermission('commerce-manageCustomers')) {
            $ret['subnav']['customers'] = [
                'label' => Craft::t('commerce', 'Customers'),
                'url' => 'commerce/customers',
            ];
        }

        if (Craft::$app->getUser()->checkPermission('commerce-manageSubscriptions') && Plugin::getInstance()->getPlans()->getAllPlans()) {
            $ret['subnav']['subscriptions'] = [
                'label' => Craft::t('commerce', 'Subscriptions'),
                'url' => 'commerce/subscriptions',
            ];
        }

        if (Craft::$app->getUser()->checkPermission('commerce-managePromotions')) {
            $ret['subnav']['promotions'] = [
                'label' => Craft::t('commerce', 'Promotions'),
                'url' => 'commerce/promotions',
            ];
        }

        if (self::getInstance()->is('pro', '>=')) {
            if (Craft::$app->getUser()->checkPermission('commerce-manageShipping')) {
                $ret['subnav']['shipping'] = [
                    'label' => Craft::t('commerce', 'Shipping'),
                    'url' => 'commerce/shipping',
                ];
            }

            if (Craft::$app->getUser()->checkPermission('commerce-manageTaxes')) {
                $ret['subnav']['tax'] = [
                    'label' => Craft::t('commerce', 'Tax'),
                    'url' => 'commerce/tax',
                ];
            }
        }

        if (Craft::$app->getUser()->checkPermission('commerce-manageStoreSettings')) {
            $ret['subnav']['store-settings'] = [
                'label' => Craft::t('commerce', 'Store Settings'),
                'url' => 'commerce/store-settings',
            ];
        }

        if (Craft::$app->getUser()->getIsAdmin() && Craft::$app->getConfig()->getGeneral()->allowAdminChanges) {
            $ret['subnav']['settings'] = [
                'label' => Craft::t('commerce', 'System Settings'),
                'url' => 'commerce/settings',
            ];
        }

        return $ret;
    }


    /**
     * @inheritdoc
     */
    protected function createSettingsModel()
    {
        return new Settings();
    }


    /**
     * Register Commerce’s twig extensions
     */
    private function _addTwigExtensions()
    {
        Craft::$app->view->registerTwigExtension(new Extension());
    }

    /**
     * Register links to product in the redactor rich text field
     */
    private function _registerRedactorLinkOptions()
    {
        if (!class_exists(RedactorField::class)) {
            return;
        }

        Event::on(RedactorField::class, RedactorField::EVENT_REGISTER_LINK_OPTIONS, function(RegisterLinkOptionsEvent $event) {
            // Include a Product link option if there are any product types that have URLs
            $productSources = [];

            $currentSiteId = Craft::$app->getSites()->getCurrentSite()->id;
            foreach ($this->getProductTypes()->getAllProductTypes() as $productType) {
                if (isset($productType->getSiteSettings()[$currentSiteId]) && $productType->getSiteSettings()[$currentSiteId]->hasUrls) {
                    $productSources[] = 'productType:' . $productType->uid;
                }
            }

            if ($productSources) {
                $event->linkOptions[] = [
                    'optionTitle' => Craft::t('commerce', 'Link to a product'),
                    'elementType' => Product::class,
                    'refHandle' => Product::refHandle(),
                    'sources' => $productSources,
                ];

                $event->linkOptions[] = [
                    'optionTitle' => Craft::t('commerce', 'Link to a variant'),
                    'elementType' => Variant::class,
                    'refHandle' => Variant::refHandle(),
                    'sources' => $productSources,
                ];
            }
        });
    }

    /**
     * Register Commerce’s permissions
     */
    private function _registerPermissions()
    {
        Event::on(UserPermissions::class, UserPermissions::EVENT_REGISTER_PERMISSIONS, function(RegisterUserPermissionsEvent $event) {
            $productTypes = Plugin::getInstance()->getProductTypes()->getAllProductTypes();

            $productTypePermissions = [];
            foreach ($productTypes as $productType) {
                $suffix = ':' . $productType->uid;
                $productTypePermissions['commerce-manageProductType' . $suffix] = ['label' => Craft::t('commerce', 'Manage “{type}” products', ['type' => $productType->name])];
            }

            $event->permissions[Craft::t('commerce', 'Craft Commerce')] = [
                'commerce-manageProducts' => ['label' => Craft::t('commerce', 'Manage products'), 'nested' => $productTypePermissions],
                'commerce-manageOrders' => [
                    'label' => Craft::t('commerce', 'Manage orders'), 'nested' => [
                        'commerce-editOrders' => [
                            'label' => Craft::t('commerce', 'Edit orders'),
                        ],
                        'commerce-deleteOrders' => [
                            'label' => Craft::t('commerce', 'Delete orders'),
                        ],
                        'commerce-capturePayment' => [
                            'label' => Craft::t('commerce', 'Capture payment'),
                        ],
                        'commerce-refundPayment' => [
                            'label' => Craft::t('commerce', 'Refund payment'),
                        ],
                    ],
                ],
                'commerce-manageCustomers' => ['label' => Craft::t('commerce', 'Manage customers')],
                'commerce-managePromotions' => ['label' => Craft::t('commerce', 'Manage promotions')],
                'commerce-manageSubscriptions' => ['label' => Craft::t('commerce', 'Manage subscriptions')],
                'commerce-manageShipping' => ['label' => Craft::t('commerce', 'Manage shipping (Pro edition Only)')],
                'commerce-manageTaxes' => ['label' => Craft::t('commerce', 'Manage taxes (Pro edition Only)')],
                'commerce-manageStoreSettings' => ['label' => Craft::t('commerce', 'Manage store settings')],
            ];
        });
    }

    /**
     * Register Commerce’s project config event listeners
     */
    private function _registerProjectConfigEventListeners()
    {
        $projectConfigService = Craft::$app->getProjectConfig();

        $gatewayService = $this->getGateways();
        $projectConfigService->onAdd(Gateways::CONFIG_GATEWAY_KEY . '.{uid}', [$gatewayService, 'handleChangedGateway'])
            ->onUpdate(Gateways::CONFIG_GATEWAY_KEY . '.{uid}', [$gatewayService, 'handleChangedGateway'])
            ->onRemove(Gateways::CONFIG_GATEWAY_KEY . '.{uid}', [$gatewayService, 'handleArchivedGateway']);

        $productTypeService = $this->getProductTypes();
        $projectConfigService->onAdd(ProductTypes::CONFIG_PRODUCTTYPES_KEY . '.{uid}', [$productTypeService, 'handleChangedProductType'])
            ->onUpdate(ProductTypes::CONFIG_PRODUCTTYPES_KEY . '.{uid}', [$productTypeService, 'handleChangedProductType'])
            ->onRemove(ProductTypes::CONFIG_PRODUCTTYPES_KEY . '.{uid}', [$productTypeService, 'handleDeletedProductType']);

        Event::on(Sites::class, Sites::EVENT_AFTER_DELETE_SITE, function(DeleteSiteEvent $event) use ($productTypeService) {
            if (!Craft::$app->getProjectConfig()->getIsApplyingYamlChanges()) {
                $productTypeService->pruneDeletedSite($event);
            }
        });

        $ordersService = $this->getOrders();
        $projectConfigService->onAdd(OrdersService::CONFIG_FIELDLAYOUT_KEY, [$ordersService, 'handleChangedFieldLayout'])
            ->onUpdate(OrdersService::CONFIG_FIELDLAYOUT_KEY, [$ordersService, 'handleChangedFieldLayout'])
            ->onRemove(OrdersService::CONFIG_FIELDLAYOUT_KEY, [$ordersService, 'handleDeletedFieldLayout']);

        $subscriptionsService = $this->getSubscriptions();
        $projectConfigService->onAdd(Subscriptions::CONFIG_FIELDLAYOUT_KEY, [$subscriptionsService, 'handleChangedFieldLayout'])
            ->onUpdate(Subscriptions::CONFIG_FIELDLAYOUT_KEY, [$subscriptionsService, 'handleChangedFieldLayout'])
            ->onRemove(Subscriptions::CONFIG_FIELDLAYOUT_KEY, [$subscriptionsService, 'handleDeletedFieldLayout']);

        $orderStatusService = $this->getOrderStatuses();
        $projectConfigService->onAdd(OrderStatuses::CONFIG_STATUSES_KEY . '.{uid}', [$orderStatusService, 'handleChangedOrderStatus'])
            ->onUpdate(OrderStatuses::CONFIG_STATUSES_KEY . '.{uid}', [$orderStatusService, 'handleChangedOrderStatus'])
            ->onRemove(OrderStatuses::CONFIG_STATUSES_KEY . '.{uid}', [$orderStatusService, 'handleDeletedOrderStatus']);

        Event::on(Emails::class, Emails::EVENT_AFTER_DELETE_EMAIL, function(EmailEvent $event) use ($orderStatusService) {
            if (!Craft::$app->getProjectConfig()->getIsApplyingYamlChanges()) {
                $orderStatusService->pruneDeletedEmail($event);
            }
        });

        $lineItemStatusService = $this->getLineItemStatuses();
        $projectConfigService->onAdd(LineItemStatuses::CONFIG_STATUSES_KEY . '.{uid}', [$lineItemStatusService, 'handleChangedLineItemStatus'])
            ->onUpdate(LineItemStatuses::CONFIG_STATUSES_KEY . '.{uid}', [$lineItemStatusService, 'handleChangedLineItemStatus'])
            ->onRemove(LineItemStatuses::CONFIG_STATUSES_KEY . '.{uid}', [$lineItemStatusService, 'handleArchivedLineItemStatus']);

        $emailService = $this->getEmails();
        $projectConfigService->onAdd(Emails::CONFIG_EMAILS_KEY . '.{uid}', [$emailService, 'handleChangedEmail'])
            ->onUpdate(Emails::CONFIG_EMAILS_KEY . '.{uid}', [$emailService, 'handleChangedEmail'])
            ->onRemove(Emails::CONFIG_EMAILS_KEY . '.{uid}', [$emailService, 'handleDeletedEmail']);

        $pdfService = $this->getPdfs();
        $projectConfigService->onAdd(Pdfs::CONFIG_PDFS_KEY . '.{uid}', [$pdfService, 'handleChangedPdf'])
            ->onUpdate(Pdfs::CONFIG_PDFS_KEY . '.{uid}', [$pdfService, 'handleChangedPdf'])
            ->onRemove(Pdfs::CONFIG_PDFS_KEY . '.{uid}', [$pdfService, 'handleDeletedPdf']);

        Event::on(ProjectConfig::class, ProjectConfig::EVENT_REBUILD, function(RebuildConfigEvent $event) {
            $event->config['commerce'] = ProjectConfigData::rebuildProjectConfig();
        });
    }

    /**
     * Register general event listeners
     */
    private function _registerCraftEventListeners()
    {
        if (!Craft::$app->getRequest()->isConsoleRequest) {
            Event::on(User::class, User::EVENT_AFTER_LOGIN, [$this->getCustomers(), 'loginHandler']);
            Event::on(User::class, User::EVENT_AFTER_LOGOUT, [$this->getCustomers(), 'logoutHandler']);
        }

        Event::on(Sites::class, Sites::EVENT_AFTER_SAVE_SITE, [$this->getProductTypes(), 'afterSaveSiteHandler']);
        Event::on(Sites::class, Sites::EVENT_AFTER_SAVE_SITE, [$this->getProducts(), 'afterSaveSiteHandler']);
        Event::on(UserElement::class, UserElement::EVENT_AFTER_SAVE, [$this->getCustomers(), 'afterSaveUserHandler'], null, false); // Lets run this before other plugins if we can
        Event::on(UserElement::class, UserElement::EVENT_BEFORE_DELETE, [$this->getSubscriptions(), 'beforeDeleteUserHandler']);
        Event::on(Purchasable::class, Elements::EVENT_BEFORE_RESTORE_ELEMENT, [$this->getPurchasables(), 'beforeRestorePurchasableHandler']);
    }

    /**
     * Register Commerce’s fields
     */
    private function _registerFieldTypes()
    {
        Event::on(Fields::class, Fields::EVENT_REGISTER_FIELD_TYPES, function(RegisterComponentTypesEvent $event) {
            $event->types[] = Products::class;
            $event->types[] = Variants::class;
        });
    }

    /**
     * Register Commerce’s widgets.
     */
    private function _registerWidgets()
    {
        Event::on(Dashboard::class, Dashboard::EVENT_REGISTER_WIDGET_TYPES, function(RegisterComponentTypesEvent $event) {
            $event->types[] = AverageOrderTotal::class;
            $event->types[] = NewCustomers::class;
            $event->types[] = Orders::class;
            $event->types[] = RepeatCustomers::class;
            $event->types[] = TotalOrders::class;
            $event->types[] = TotalOrdersByCountry::class;
            $event->types[] = TopCustomers::class;
            $event->types[] = TopProducts::class;
            $event->types[] = TopProductTypes::class;
            $event->types[] = TopPurchasables::class;
            $event->types[] = TotalRevenue::class;
        });
    }

    /**
     * Register Commerce’s template variable.
     */
    private function _registerVariables()
    {
        Event::on(CraftVariable::class, CraftVariable::EVENT_INIT, function(Event $event) {
            /** @var CraftVariable $variable */
            $variable = $event->sender;
            $variable->attachBehavior('commerce', CraftVariableBehavior::class);
        });
    }

    /**
     * Register for FK restore plugin
     */
    private function _registerForeignKeysRestore()
    {
        if (!class_exists(RestoreController::class)) {
            return;
        }

        Event::on(RestoreController::class, RestoreController::EVENT_AFTER_RESTORE_FKS, function(Event $event) {
            // Add default FKs
            (new Install())->addForeignKeys();
        });
    }

    /**
     * Register the powered-by header
     */
    private function _registerPoweredByHeader()
    {
        if (!Craft::$app->request->isConsoleRequest) {
            $headers = Craft::$app->getResponse()->getHeaders();
            // Send the X-Powered-By header?
            if (Craft::$app->getConfig()->getGeneral()->sendPoweredByHeader) {
                $original = $headers->get('X-Powered-By');
                $headers->set('X-Powered-By', $original . ($original ? ',' : '') . 'Craft Commerce');
            } else {
                // In case PHP is already setting one
                header_remove('X-Powered-By');
            }
        }
    }

    /**
     * Register the element types supplied by Craft Commerce
     */
    private function _registerElementTypes()
    {
        Event::on(Elements::class, Elements::EVENT_REGISTER_ELEMENT_TYPES, function(RegisterComponentTypesEvent $e) {
            $e->types[] = Variant::class;
            $e->types[] = Product::class;
            $e->types[] = Order::class;
            $e->types[] = Subscription::class;
            $e->types[] = Donation::class;
        });
    }

    /**
     * Register the Gql interfaces
     */
    private function _registerGqlInterfaces()
    {
        Event::on(Gql::class, Gql::EVENT_REGISTER_GQL_TYPES, function(RegisterGqlTypesEvent $event) {
            // Add my GraphQL types
            $types = $event->types;
            $types[] = GqlProductInterface::class;
            $types[] = GqlVariantInterface::class;
            $event->types = $types;
        });
    }

    /**
     * Register the Gql queries
     */
    private function _registerGqlQueries()
    {
        Event::on(Gql::class, Gql::EVENT_REGISTER_GQL_QUERIES, function(RegisterGqlQueriesEvent $event) {
            // Add my GraphQL queries
            $event->queries = array_merge(
                $event->queries,
                GqlProductQueries::getQueries(),
                GqlVariantQueries::getQueries()
            );
        });
    }

    /**
     * Register the Gql permissions
     */
    private function _registerGqlComponents()
    {
        Event::on(Gql::class, Gql::EVENT_REGISTER_GQL_SCHEMA_COMPONENTS, function(RegisterGqlSchemaComponentsEvent $event) {
            $queryComponents = [];

            $productTypes = Plugin::getInstance()->getProductTypes()->getAllProductTypes();

            if (!empty($productTypes)) {
                $label = Craft::t('commerce', 'Products');
                $productPermissions = [];

                foreach ($productTypes as $productType) {
                    $suffix = 'productTypes.' . $productType->uid;
                    $productPermissions[$suffix . ':read'] = ['label' => Craft::t('commerce', 'View product type - {productType}', ['productType' => Craft::t('site', $productType->name)])];
                }

                $queryComponents[$label] = $productPermissions;
            }

            $event->queries = array_merge($event->queries, $queryComponents);
        });
    }

    private function _registerGqlEagerLoadableFields()
    {
        Event::on(ElementQueryConditionBuilder::class, ElementQueryConditionBuilder::EVENT_REGISTER_GQL_EAGERLOADABLE_FIELDS, function(RegisterGqlEagerLoadableFields $event) {
            $event->fieldList['variants'] = [Products::class];
            $event->fieldList['product'] = [Variants::class];
        });
    }

    /**
     * Register the cache types
     */
    private function _registerCacheTypes()
    {
        // create the directory if it doesn't exist

        $path = Craft::$app->getPath()->getRuntimePath() . DIRECTORY_SEPARATOR . 'commerce-order-exports';

        try {
            FileHelper::createDirectory($path);
        } catch (\Exception $e) {
            Craft::error($e->getMessage());
        }

        Event::on(ClearCaches::class, ClearCaches::EVENT_REGISTER_CACHE_OPTIONS, function(RegisterCacheOptionsEvent $e) use ($path) {
            try {
                FileHelper::createDirectory($path);
            } catch (\Exception $e) {
                Craft::error($e->getMessage());
            }

            $e->options[] = [
                'key' => 'commerce-order-exports',
                'label' => Craft::t('commerce', 'Commerce order exports'),
                'action' => static function() use ($path) {
                    if (file_exists($path)) {
                        FileHelper::clearDirectory($path);
                    }
                },
            ];
        });
    }

    /**
     * Register the things that need to be garbage collected
     *
     * @since 2.2
     */
    private function _registerGarbageCollection()
    {
        Event::on(Gc::class, Gc::EVENT_RUN, function(Event $event) {
            // Deletes carts that meet the purge settings
            if (Craft::$app instanceof ConsoleApplication) {
                Console::stdout('    > purging inactive carts ... ');
            }
            Plugin::getInstance()->getCarts()->purgeIncompleteCarts();
            if (Craft::$app instanceof ConsoleApplication) {
                Console::stdout("done\n", Console::FG_GREEN);
            }

            // Deletes customers that are not related to any cart/order or user
            if (Craft::$app instanceof ConsoleApplication) {
                Console::stdout('    > purging orphaned customers ... ');
            }
            Plugin::getInstance()->getCustomers()->purgeOrphanedCustomers();
            if (Craft::$app instanceof ConsoleApplication) {
                Console::stdout("done\n", Console::FG_GREEN);
            }

            // Deletes addresses that are not related to customers, carts or orders
            if (Craft::$app instanceof ConsoleApplication) {
                Console::stdout('    > purging orphaned addresses ... ');
            }
            Plugin::getInstance()->getAddresses()->purgeOrphanedAddresses();
            if (Craft::$app instanceof ConsoleApplication) {
                Console::stdout("done\n", Console::FG_GREEN);
            }

            // Delete partial elements
            /** @var Gc $gc */
            $gc = $event->sender;
            $gc->deletePartialElements(Donation::class, Table::DONATIONS, 'id');
            $gc->deletePartialElements(Order::class, Table::ORDERS, 'id');
            $gc->deletePartialElements(Product::class, Table::PRODUCTS, 'id');
            $gc->deletePartialElements(Subscription::class, Table::SUBSCRIPTIONS, 'id');
            $gc->deletePartialElements(Variant::class, Table::VARIANTS, 'id');
        });
    }

    /**
     * Register the element exportables
     *
     * @since 2.2
     */
    private function _registerElementExports()
    {
        Event::on(Order::class, Order::EVENT_REGISTER_EXPORTERS, function(RegisterElementExportersEvent $e) {
            $e->exporters[] = OrderExport::class;
            $e->exporters[] = LineItemExport::class;
        });
    }

    /**
     * Registers additional standard fields for the product and variant field layout designers.
     *
     * @since 3.2.0
     */
    private function _defineFieldLayoutElements()
    {
        Event::on(FieldLayout::class, FieldLayout::EVENT_DEFINE_STANDARD_FIELDS, function(DefineFieldLayoutFieldsEvent $e) {
            /** @var FieldLayout $fieldLayout */
            $fieldLayout = $e->sender;

            switch ($fieldLayout->type) {
                case Product::class:
                    $e->fields[] = ProductTitleField::class;
                    $e->fields[] = VariantsField::class;
                    break;
                case Variant::class:
                    $e->fields[] = VariantTitleField::class;
            }
        });
    }

    /**
     * Defines the `resave/products` command.
     */
    private function _defineResaveCommand()
    {
        if (
            !Craft::$app instanceof ConsoleApplication ||
            version_compare(Craft::$app->version, '3.2.0-beta.3', '<')
        ) {
            return;
        }

        Event::on(ResaveController::class, ConsoleController::EVENT_DEFINE_ACTIONS, function(DefineConsoleActionsEvent $e) {
            $e->actions['products'] = [
                'action' => function(): int {
                    /** @var ResaveController $controller */
                    $controller = Craft::$app->controller;
                    $query = Product::find();
                    if ($controller->type !== null) {
                        $query->type(explode(',', $controller->type));
                    }
                    return $controller->saveElements($query);
                },
                'options' => ['type'],
                'helpSummary' => 'Re-saves Commerce products.',
                'optionsHelp' => [
                    'type' => 'The product type handle(s) of the products to resave.',
                ],
            ];

            $e->actions['orders'] = [
                'action' => function(): int {
                    /** @var ResaveController $controller */
                    $controller = Craft::$app->controller;
                    $query = Order::find();
                    $query->isCompleted(true);
                    return $controller->saveElements($query);
                },
                'options' => [],
                'helpSummary' => 'Re-saves completed Commerce orders.',
            ];

            $e->actions['carts'] = [
                'action' => function(): int {
                    /** @var ResaveController $controller */
                    $controller = Craft::$app->controller;
                    $query = Order::find();
                    $query->isCompleted(false);
                    return $controller->saveElements($query);
                },
                'options' => [],
                'helpSummary' => 'Re-saves Commerce carts.',
            ];
        });
    }

    /**
     * Registers templates hooks for inserting Commerce information in the control panel
     *
     * @since 2.2
     */
    private function _registerTemplateHooks()
    {
        if ($this->getSettings()->showCustomerInfoTab) {
            Craft::$app->getView()->hook('cp.users.edit', [$this->getCustomers(), 'addEditUserCustomerInfoTab']);
            Craft::$app->getView()->hook('cp.users.edit.content', [$this->getCustomers(), 'addEditUserCustomerInfoTabContent']);
        }
    }
}
