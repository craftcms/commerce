<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce;

use Craft;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\ckeditor\events\DefineLinkOptionsEvent;
use craft\ckeditor\Field as CKEditorField;
use craft\commerce\base\Purchasable;
use craft\commerce\behaviors\CustomerAddressBehavior;
use craft\commerce\behaviors\CustomerBehavior;
use craft\commerce\behaviors\ValidateOrganizationTaxIdBehavior;
use craft\commerce\db\Table;
use craft\commerce\debug\CommercePanel;
use craft\commerce\elements\Donation;
use craft\commerce\elements\Order;
use craft\commerce\elements\Product;
use craft\commerce\elements\Subscription;
use craft\commerce\elements\Variant;
use craft\commerce\events\EmailEvent;
use craft\commerce\exports\LineItemExport;
use craft\commerce\exports\OrderExport;
use craft\commerce\fieldlayoutelements\ProductTitleField;
use craft\commerce\fieldlayoutelements\UserAddressSettings;
use craft\commerce\fieldlayoutelements\VariantsField as VariantsLayoutElement;
use craft\commerce\fieldlayoutelements\VariantTitleField;
use craft\commerce\fields\Products as ProductsField;
use craft\commerce\fields\Variants as VariantsField;
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
use craft\commerce\services\Carts;
use craft\commerce\services\Coupons;
use craft\commerce\services\Currencies;
use craft\commerce\services\Customers;
use craft\commerce\services\Discounts;
use craft\commerce\services\Emails;
use craft\commerce\services\Formulas;
use craft\commerce\services\Gateways;
use craft\commerce\services\LineItems;
use craft\commerce\services\LineItemStatuses;
use craft\commerce\services\OrderAdjustments;
use craft\commerce\services\OrderHistories;
use craft\commerce\services\OrderNotices;
use craft\commerce\services\Orders as OrdersService;
use craft\commerce\services\OrderStatuses;
use craft\commerce\services\PaymentCurrencies;
use craft\commerce\services\Payments;
use craft\commerce\services\PaymentSources;
use craft\commerce\services\Pdfs;
use craft\commerce\services\Plans;
use craft\commerce\services\Products;
use craft\commerce\services\ProductTypes;
use craft\commerce\services\Purchasables;
use craft\commerce\services\Sales;
use craft\commerce\services\ShippingCategories;
use craft\commerce\services\ShippingMethods;
use craft\commerce\services\ShippingRuleCategories;
use craft\commerce\services\ShippingRules;
use craft\commerce\services\ShippingZones;
use craft\commerce\services\Store;
use craft\commerce\services\Subscriptions;
use craft\commerce\services\TaxCategories;
use craft\commerce\services\Taxes;
use craft\commerce\services\TaxRates;
use craft\commerce\services\TaxZones;
use craft\commerce\services\Transactions;
use craft\commerce\services\Variants as VariantsService;
use craft\commerce\services\Webhooks;
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
use craft\debug\Module;
use craft\elements\Address;
use craft\elements\User as UserElement;
use craft\events\AuthorizationCheckEvent;
use craft\events\DefineBehaviorsEvent;
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
use craft\helpers\Db;
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
use craft\services\ProjectConfig;
use craft\services\Sites;
use craft\services\UserPermissions;
use craft\utilities\ClearCaches;
use craft\web\Application;
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
    public const EDITION_PRO = 'pro';

    public static function config(): array
    {
        return [
            'components' => [
                'carts' => ['class' => Carts::class],
                'coupons' => ['class' => Coupons::class],
                'currencies' => ['class' => Currencies::class],
                'discounts' => ['class' => Discounts::class],
                'emails' => ['class' => Emails::class],
                'formulas' => ['class' => Formulas::class],
                'gateways' => ['class' => Gateways::class],
                'lineItems' => ['class' => LineItems::class],
                'lineItemStatuses' => ['class' => LineItemStatuses::class],
                'orderAdjustments' => ['class' => OrderAdjustments::class],
                'orderHistories' => ['class' => OrderHistories::class],
                'orders' => ['class' => OrdersService::class],
                'orderNotices' => ['class' => OrderNotices::class],
                'orderStatuses' => ['class' => OrderStatuses::class],
                'paymentMethods' => ['class' => Gateways::class],
                'paymentCurrencies' => ['class' => PaymentCurrencies::class],
                'payments' => ['class' => Payments::class],
                'paymentSources' => ['class' => PaymentSources::class],
                'pdfs' => ['class' => Pdfs::class],
                'plans' => ['class' => Plans::class],
                'products' => ['class' => Products::class],
                'productTypes' => ['class' => ProductTypes::class],
                'purchasables' => ['class' => Purchasables::class],
                'sales' => ['class' => Sales::class],
                'shippingMethods' => ['class' => ShippingMethods::class],
                'shippingRules' => ['class' => ShippingRules::class],
                'shippingRuleCategories' => ['class' => ShippingRuleCategories::class],
                'shippingCategories' => ['class' => ShippingCategories::class],
                'shippingZones' => ['class' => ShippingZones::class],
                'store' => ['class' => Store::class],
                'subscriptions' => ['class' => Subscriptions::class],
                'taxCategories' => ['class' => TaxCategories::class],
                'taxes' => ['class' => Taxes::class],
                'taxRates' => ['class' => TaxRates::class],
                'taxZones' => ['class' => TaxZones::class],
                'transactions' => ['class' => Transactions::class],
                'customers' => ['class' => Customers::class],
                'variants' => ['class' => VariantsService::class],
                'webhooks' => ['class' => Webhooks::class],
            ],
        ];
    }

    public static function editions(): array
    {
        return [
            self::EDITION_PRO,
        ];
    }

    /**
     * @inheritDoc
     */
    public string $schemaVersion = '4.5.1';

    /**
     * @inheritdoc
     */
    public bool $hasCpSettings = true;

    /**
     * @inheritdoc
     */
    public bool $hasCpSection = true;

    /**
     * @inheritdoc
     */
    public string $minVersionRequired = '3.4.11';

    use CommerceServices;
    use Variables;
    use Routes;

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        $request = Craft::$app->getRequest();

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
        $this->_registerDebugPanels();

        if ($request->getIsConsoleRequest()) {
            $this->_defineResaveCommand();
        } elseif ($request->getIsCpRequest()) {
            $this->_registerCpRoutes();
            $this->_registerStoreAddressAuthHandlers();
            $this->_registerWidgets();
            $this->_registerElementExports();
            $this->_defineFieldLayoutElements();
            $this->_registerTemplateHooks();
            $this->_registerRedactorLinkOptions();
            $this->_registerCKEditorLinkOptions();
        } else {
            $this->_registerSiteRoutes();
        }

        Craft::setAlias('@commerceLib', Craft::getAlias('@craft/commerce/../lib'));
    }

    /**
     * @inheritdoc
     */
    public function beforeInstall(): void
    {
        // Check version before installing
        if (version_compare(Craft::$app->getInfo()->version, '4.0', '<')) {
            throw new Exception('Craft Commerce 4 requires Craft CMS 4+ in order to run.');
        }

        if (!defined('PHP_VERSION_ID') || PHP_VERSION_ID < 80000) {
            Craft::error('Craft Commerce requires PHP 8.0.2+ in order to run.');
        }
    }

    /**
     * @inheritdoc
     */
    public function getSettingsResponse(): mixed
    {
        return Craft::$app->getResponse()->redirect(UrlHelper::cpUrl('commerce/settings/general'));
    }

    /**
     * @inheritdoc
     */
    public function getCpNavItem(): ?array
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
        if ($hasEditableProductTypes) {
            $ret['subnav']['products'] = [
                'label' => Craft::t('commerce', 'Products'),
                'url' => 'commerce/products',
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
    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }


    /**
     * Register Commerce’s twig extensions
     */
    private function _addTwigExtensions(): void
    {
        Craft::$app->view->registerTwigExtension(new Extension());
    }

    /**
     * Register links to product in the redactor rich text field
     */
    private function _registerRedactorLinkOptions(): void
    {
        if (!class_exists(RedactorField::class)) {
            return;
        }

        Event::on(RedactorField::class, RedactorField::EVENT_REGISTER_LINK_OPTIONS, function(RegisterLinkOptionsEvent $event) {
            // Include a Product link option if there are any product types that have URLs
            $productSources = [];

            $sites = Craft::$app->getSites()->getAllSites();

            foreach ($this->getProductTypes()->getAllProductTypes() as $productType) {
                foreach ($sites as $site) {
                    $productTypeSettings = $productType->getSiteSettings();
                    if (isset($productTypeSettings[$site->id]) && $productTypeSettings[$site->id]->hasUrls) {
                        $productSources[] = 'productType:' . $productType->uid;
                    }
                }
            }

            $productSources = array_unique($productSources);

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
     * Register links to product in the ckeditor rich text field
     */
    private function _registerCKEditorLinkOptions(): void
    {
        $ckEditorPlugin = Craft::$app->getPlugins()->getPlugin('ckeditor');
        if (!class_exists(CKEditorField::class) || !$ckEditorPlugin || version_compare($ckEditorPlugin->getVersion(), '3.0', '<')) {
            return;
        }

        Event::on(CKEditorField::class, CKEditorField::EVENT_DEFINE_LINK_OPTIONS, function(DefineLinkOptionsEvent $event) {
            // Include a Product link option if there are any product types that have URLs
            $productSources = [];

            $sites = Craft::$app->getSites()->getAllSites();

            foreach ($this->getProductTypes()->getAllProductTypes() as $productType) {
                foreach ($sites as $site) {
                    $productTypeSettings = $productType->getSiteSettings();
                    if (isset($productTypeSettings[$site->id]) && $productTypeSettings[$site->id]->hasUrls) {
                        $productSources[] = 'productType:' . $productType->uid;
                    }
                }
            }

            $productSources = array_unique($productSources);

            if ($productSources) {
                $event->linkOptions[] = [
                    'label' => Craft::t('commerce', 'Link to a product'),
                    'elementType' => Product::class,
                    'refHandle' => Product::refHandle(),
                    'sources' => $productSources,
                ];

                $event->linkOptions[] = [
                    'label' => Craft::t('commerce', 'Link to a variant'),
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
    private function _registerPermissions(): void
    {
        Event::on(UserPermissions::class, UserPermissions::EVENT_REGISTER_PERMISSIONS, function(RegisterUserPermissionsEvent $event) {
            $event->permissions[] = [
                'heading' => Craft::t('commerce', 'Craft Commerce'),
                'permissions' => $this->_registerProductTypePermission() + [
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
                        'commerce-managePromotions' => $this->_registerPromotionPermission(),
                        'commerce-manageSubscriptions' => ['label' => Craft::t('commerce', 'Manage subscriptions')],
                        'commerce-manageShipping' => ['label' => Craft::t('commerce', 'Manage shipping (Pro edition only)')],
                        'commerce-manageTaxes' => ['label' => Craft::t('commerce', 'Manage taxes (Pro edition only)')],
                        'commerce-manageStoreSettings' => ['label' => Craft::t('commerce', 'Manage store settings')],
                    ],
            ];
        });
    }

    /**
     * @return array
     */
    private function _registerProductTypePermission(): array
    {
        $productTypes = self::getInstance()->getProductTypes()->getAllProductTypes();

        $productTypePermissions = [];
        foreach ($productTypes as $productType) {
            $suffix = ':' . $productType->uid;

            $productTypePermissions['commerce-editProductType' . $suffix] = [
                'label' => Craft::t('commerce', 'Edit “{type}” products', ['type' => $productType->name]),
                'nested' => [
                    "commerce-createProducts$suffix" => [
                        'label' => Craft::t('commerce', 'Create products'),
                    ],
                    "commerce-deleteProducts$suffix" => [
                        'label' => Craft::t('commerce', 'Delete products'),
                    ],
                ],
            ];
        }

        return $productTypePermissions;
    }

    /**
     * @return array
     */
    private function _registerPromotionPermission(): array
    {
        return [
            'label' => Craft::t('commerce', 'Manage promotions'),
            'nested' => [
                'commerce-editSales' => ['label' => Craft::t('commerce', 'Edit sales')],
                'commerce-createSales' => ['label' => Craft::t('commerce', 'Create sales')],
                'commerce-deleteSales' => ['label' => Craft::t('commerce', 'Delete sales')],
                'commerce-editDiscounts' => ['label' => Craft::t('commerce', 'Edit discounts')],
                'commerce-createDiscounts' => ['label' => Craft::t('commerce', 'Create discounts')],
                'commerce-deleteDiscounts' => ['label' => Craft::t('commerce', 'Delete discounts')],
            ],
        ];
    }

    /**
     * Register Commerce’s project config event listeners
     */
    private function _registerProjectConfigEventListeners(): void
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
            if (!Craft::$app->getProjectConfig()->getIsApplyingExternalChanges()) {
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
            if (!Craft::$app->getProjectConfig()->getIsApplyingExternalChanges()) {
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

        Event::on(ProjectConfig::class, ProjectConfig::EVENT_REBUILD, static function(RebuildConfigEvent $event) {
            $event->config['commerce'] = ProjectConfigData::rebuildProjectConfig();
        });
    }

    /**
     * Register general event listeners
     */
    private function _registerCraftEventListeners(): void
    {
        if (!Craft::$app->getRequest()->isConsoleRequest) {
            Event::on(User::class, User::EVENT_AFTER_LOGIN, [$this->getCustomers(), 'loginHandler']);
            Event::on(User::class, User::EVENT_AFTER_LOGOUT, [$this->getCarts(), 'forgetCart']);
        }

        Event::on(Sites::class, Sites::EVENT_AFTER_SAVE_SITE, [$this->getProductTypes(), 'afterSaveSiteHandler']);
        Event::on(Sites::class, Sites::EVENT_AFTER_SAVE_SITE, [$this->getProducts(), 'afterSaveSiteHandler']);

        Event::on(UserElement::class, UserElement::EVENT_BEFORE_DELETE, [$this->getSubscriptions(), 'beforeDeleteUserHandler']);
        Event::on(UserElement::class, UserElement::EVENT_BEFORE_DELETE, [$this->getOrders(), 'beforeDeleteUserHandler']);

        Event::on(Address::class, Address::EVENT_AFTER_SAVE, [$this->getOrders(), 'afterSaveAddressHandler']);

        Event::on(
            UserElement::class,
            UserElement::EVENT_DEFINE_BEHAVIORS,
            function(DefineBehaviorsEvent $event) {
                $event->behaviors['commerce:customer'] = CustomerBehavior::class;
            }
        );

        Event::on(Address::class, Address::EVENT_DEFINE_BEHAVIORS, function(DefineBehaviorsEvent $event) {
            /** @var Address $address */
            $address = $event->sender;
            $owner = $address->getOwner();
            if ($owner instanceof UserElement) {
                $event->behaviors['commerce:address'] = CustomerAddressBehavior::class;
            }

            if (self::getInstance()->getSettings()->validateBusinessTaxIdAsVatId) {
                $event->behaviors['commerce:validateOrganizationTaxId'] = ValidateOrganizationTaxIdBehavior::class;
            }
        });

        Event::on(Purchasable::class, Elements::EVENT_BEFORE_RESTORE_ELEMENT, [$this->getPurchasables(), 'beforeRestorePurchasableHandler']);
    }

    /**
     * Registers store address authorization event handlers
     */
    private function _registerStoreAddressAuthHandlers(): void
    {
        $checkAuth = function(AuthorizationCheckEvent $event) {
            /** @var Address $address */
            $address = $event->sender;
            $canonicalId = $address->getCanonicalId();
            if (
                $canonicalId && $canonicalId === Plugin::getInstance()->getStore()->getStore()->getLocationAddressId() &&
                $event->user->can('commerce-manageStoreSettings')
            ) {
                $event->authorized = true;
                $event->handled = true;
            }
        };

        Event::on(Address::class, Address::EVENT_AUTHORIZE_VIEW, $checkAuth);
        Event::on(Address::class, Address::EVENT_AUTHORIZE_SAVE, $checkAuth);
    }

    /**
     * Register Commerce’s fields
     */
    private function _registerFieldTypes(): void
    {
        Event::on(Fields::class, Fields::EVENT_REGISTER_FIELD_TYPES, static function(RegisterComponentTypesEvent $event) {
            $event->types[] = ProductsField::class;
            $event->types[] = VariantsField::class;
        });
    }

    /**
     * Register Commerce’s widgets.
     */
    private function _registerWidgets(): void
    {
        Event::on(Dashboard::class, Dashboard::EVENT_REGISTER_WIDGET_TYPES, static function(RegisterComponentTypesEvent $event) {
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
    private function _registerVariables(): void
    {
        Event::on(CraftVariable::class, CraftVariable::EVENT_INIT, static function(Event $event) {
            /** @var CraftVariable $variable */
            $variable = $event->sender;
            $variable->attachBehavior('commerce', CraftVariableBehavior::class);
        });
    }

    /**
     * Register for FK restore plugin
     */
    private function _registerForeignKeysRestore(): void
    {
        if (!class_exists(RestoreController::class)) {
            return;
        }

        Event::on(RestoreController::class, RestoreController::EVENT_AFTER_RESTORE_FKS, static function() {
            // Add default FKs
            (new Install())->addForeignKeys();
        });
    }

    /**
     * Register the powered-by header
     */
    private function _registerPoweredByHeader(): void
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
    private function _registerElementTypes(): void
    {
        Event::on(Elements::class, Elements::EVENT_REGISTER_ELEMENT_TYPES, static function(RegisterComponentTypesEvent $e) {
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
    private function _registerGqlInterfaces(): void
    {
        Event::on(Gql::class, Gql::EVENT_REGISTER_GQL_TYPES, static function(RegisterGqlTypesEvent $event) {
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
    private function _registerGqlQueries(): void
    {
        Event::on(Gql::class, Gql::EVENT_REGISTER_GQL_QUERIES, static function(RegisterGqlQueriesEvent $event) {
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
    private function _registerGqlComponents(): void
    {
        Event::on(Gql::class, Gql::EVENT_REGISTER_GQL_SCHEMA_COMPONENTS, static function(RegisterGqlSchemaComponentsEvent $event) {
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

    private function _registerGqlEagerLoadableFields(): void
    {
        Event::on(ElementQueryConditionBuilder::class, ElementQueryConditionBuilder::EVENT_REGISTER_GQL_EAGERLOADABLE_FIELDS, function(RegisterGqlEagerLoadableFields $event) {
            $event->fieldList['variants'] = [ProductsField::class];
            $event->fieldList['product'] = [VariantsField::class];
        });
    }

    /**
     * Register the cache types
     */
    private function _registerCacheTypes(): void
    {
        // create the directory if it doesn't exist

        $path = Craft::$app->getPath()->getRuntimePath() . DIRECTORY_SEPARATOR . 'commerce-order-exports';

        try {
            FileHelper::createDirectory($path);
        } catch (\Exception $e) {
            Craft::error($e->getMessage());
        }

        Event::on(ClearCaches::class, ClearCaches::EVENT_REGISTER_CACHE_OPTIONS, static function(RegisterCacheOptionsEvent $e) use ($path) {
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
    private function _registerGarbageCollection(): void
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

            // Delete orphaned variants
            Db::delete(Table::VARIANTS, ['productId' => null]);

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
    private function _registerElementExports(): void
    {
        Event::on(Order::class, Order::EVENT_REGISTER_EXPORTERS, static function(RegisterElementExportersEvent $e) {
            $e->exporters[] = OrderExport::class;
            $e->exporters[] = LineItemExport::class;
        });
    }

    /**
     * Register Commerce related debug panels.
     *
     * @since 4.0
     */
    private function _registerDebugPanels(): void
    {
        Event::on(Application::class, Application::EVENT_BEFORE_REQUEST, static function() {
            /** @var Module|null $module */
            $module = Craft::$app->getModule('debug');
            $user = Craft::$app->getUser()->getIdentity();

            if (!$module || !$user || !Craft::$app->getConfig()->getGeneral()->devMode) {
                return;
            }

            $pref = Craft::$app->getRequest()->getIsCpRequest() ? 'enableDebugToolbarForCp' : 'enableDebugToolbarForSite';
            if (!$user->getPreference($pref)) {
                return;
            }

            $module->panels['commerce'] = new CommercePanel([
                'id' => 'commerce',
                'module' => $module,
                'cart' => !Craft::$app->getRequest()->getIsCpRequest() ? Plugin::getInstance()->getCarts()->getCart() : null,
            ]);
        });
    }

    /**
     * Registers additional standard fields for the product and variant field layout designers.
     *
     * @since 3.2.0
     */
    private function _defineFieldLayoutElements(): void
    {
        Event::on(FieldLayout::class, FieldLayout::EVENT_DEFINE_NATIVE_FIELDS, static function(DefineFieldLayoutFieldsEvent $e) {
            /** @var FieldLayout $fieldLayout */
            $fieldLayout = $e->sender;

            switch ($fieldLayout->type) {
                case Address::class:
                    $e->fields[] = UserAddressSettings::class;
                    break;
                case Product::class:
                    $e->fields[] = ProductTitleField::class;
                    $e->fields[] = VariantsLayoutElement::class;
                    break;
                case Variant::class:
                    $e->fields[] = VariantTitleField::class;
            }
        });
    }

    /**
     * Defines the `resave/products` command.
     */
    private function _defineResaveCommand(): void
    {
        Event::on(ResaveController::class, ConsoleController::EVENT_DEFINE_ACTIONS, static function(DefineConsoleActionsEvent $e) {
            $e->actions['products'] = [
                'action' => function(): int {
                    /** @var ResaveController $controller */
                    $controller = Craft::$app->controller;
                    $criteria = [];
                    if ($controller->type !== null) {
                        $criteria['type'] = explode(',', $controller->type);
                    }
                    return $controller->resaveElements(Product::class, $criteria);
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
                    return $controller->resaveElements(Order::class, [
                        'isCompleted' => true,
                    ]);
                },
                'options' => [],
                'helpSummary' => 'Re-saves completed Commerce orders.',
            ];

            $e->actions['carts'] = [
                'action' => function(): int {
                    /** @var ResaveController $controller */
                    $controller = Craft::$app->controller;
                    return $controller->resaveElements(Order::class, [
                        'isCompleted' => false,
                    ]);
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
    private function _registerTemplateHooks(): void
    {
        if ($this->getSettings()->showEditUserCommerceTab) {
            Craft::$app->getView()->hook('cp.users.edit', [$this->getCustomers(), 'addEditUserCommerceTab']);
            Craft::$app->getView()->hook('cp.users.edit.content', [$this->getCustomers(), 'addEditUserCommerceTabContent']);
        }
    }
}
