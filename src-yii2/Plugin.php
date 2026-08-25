<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce;

use Craft;
use craft\base\Model;
use craft\ckeditor\events\DefineLinkOptionsEvent;
use craft\ckeditor\Field as CKEditorField;
use craft\commerce\base\Purchasable;
use craft\commerce\db\Table;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\commerce\events\EmailEvent;
use craft\commerce\gql\interfaces\elements\Product as GqlProductInterface;
use craft\commerce\gql\interfaces\elements\Variant as GqlVariantInterface;
use craft\commerce\gql\queries\Product as GqlProductQueries;
use craft\commerce\gql\queries\Variant as GqlVariantQueries;
use CraftCms\Commerce\Gql\Types\Input\Criteria\ProductRelation;
use CraftCms\Commerce\Gql\Types\Input\Criteria\VariantRelation;
use craft\commerce\helpers\ProjectConfigData;
use craft\commerce\migrations\Install;
use craft\commerce\models\Settings;
use craft\commerce\plugin\Routes;
use craft\commerce\services\Emails;
use craft\commerce\services\Gateways;
use craft\commerce\services\LineItemStatuses;
use craft\commerce\services\OrderStatuses;
use craft\commerce\services\Orders as OrdersService;
use craft\commerce\services\Pdfs;
use craft\commerce\services\ProductTypes;
use craft\commerce\services\Stores;
use craft\commerce\services\Transfers as TransfersService;
use craft\commerce\web\twig\CraftVariableBehavior;
use craft\elements\db\UserQuery;
use CraftCms\Cms\Edition as CmsEdition;
use craft\events\DeleteSiteEvent;
use craft\events\PopulateElementsEvent;
use craft\events\RebuildConfigEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterGqlQueriesEvent;
use craft\events\RegisterGqlTypesEvent;
use craft\fixfks\controllers\RestoreController;
use craft\helpers\ArrayHelper;
use craft\helpers\UrlHelper;
use craft\redactor\events\RegisterLinkOptionsEvent;
use craft\redactor\Field as RedactorField;
use craft\services\Elements;
use craft\services\Gql;
use craft\services\ProjectConfig;
use craft\services\Sites;
use craft\web\Application;
use craft\web\twig\variables\CraftVariable;
use CraftCms\Commerce\Plugin as BasePlugin;
use Exception;
use yii\base\Event;

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
    public const EDITION_PRO = 'pro';
    public const EDITION_ENTERPRISE = 'enterprise';

    public const EDITION_PRO_STORE_LIMIT = 5;

    /**
     * Returns the editions for Craft Commerce
     *
     * @inheritDoc
     */
    public static function editions(): array
    {
        return [
            self::EDITION_PRO,
            self::EDITION_ENTERPRISE,
        ];
    }

    /**
     * @inheritDoc
     */
    public string $schemaVersion = '5.7.0.0';

    /**
     * @inheritdoc
     */
    public bool $hasCpSettings = true;

    /**
     * @inheritdoc
     */
    public string $minVersionRequired = '3.4.11';

    /**
     * @inheritdoc
     */
    public CmsEdition $minCmsEdition = CmsEdition::Pro;

    /**
     * @inheritdoc
     */
    public bool $hasReadOnlyCpSettings = true;

    use Routes;

    public function boot(): void
    {
        parent::boot();

        $request = Craft::$app->getRequest();

        $this->_registerCraftEventListeners();
        $this->_registerProjectConfigEventListeners();
        $this->_registerVariables();
        $this->_registerForeignKeysRestore();
        $this->_registerPoweredByHeader();
        $this->_registerGqlInterfaces();
        $this->_registerGqlQueries();
        $this->_registerRelatedToArguments();

        if ($request->getIsCpRequest()) {
            $this->_registerCpRoutes();
            $this->_registerRedactorLinkOptions();
            $this->_registerCKEditorLinkOptions();
        } else {
            $this->_registerSiteRoutes();
        }

        Craft::setAlias('@commerceLib', Craft::getAlias('@craft/commerce/../lib'));
    }

    public function beforeInstall(): void
    {
        // Check version before installing
        if (version_compare(Craft::$app->getInfo()->version, '5.1.0', '<')) {
            throw new Exception('Craft Commerce 5 requires Craft CMS 5.1+ in order to run.');
        }

        if (!defined('PHP_VERSION_ID') || PHP_VERSION_ID < 80200) {
            Craft::error('Craft Commerce requires PHP 8.2.0+ in order to run.');
        }
    }

    public function getSettingsResponse(): mixed
    {
        return Craft::$app->getResponse()->redirect(UrlHelper::cpUrl('commerce/settings/general'));
    }

    public function getReadOnlySettingsResponse(): mixed
    {
        return Craft::$app->getResponse()->redirect(UrlHelper::cpUrl('commerce/settings/general'));
    }

    protected function createSettingsModel(): ?Model
    {
        return new Settings();
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
     * Register Commerce’s project config event listeners
     */
    private function _registerProjectConfigEventListeners(): void
    {
        $projectConfigService = Craft::$app->getProjectConfig();

        $gatewayService = $this->getGateways();
        $projectConfigService->onAdd(Gateways::CONFIG_GATEWAY_KEY . '.{uid}', $gatewayService->handleChangedGateway(...))
            ->onUpdate(Gateways::CONFIG_GATEWAY_KEY . '.{uid}', $gatewayService->handleChangedGateway(...))
            ->onRemove(Gateways::CONFIG_GATEWAY_KEY . '.{uid}', $gatewayService->handleArchivedGateway(...));

        $productTypeService = $this->getProductTypes();
        $projectConfigService->onAdd(ProductTypes::CONFIG_PRODUCTTYPES_KEY . '.{uid}', $productTypeService->handleChangedProductType(...))
            ->onUpdate(ProductTypes::CONFIG_PRODUCTTYPES_KEY . '.{uid}', $productTypeService->handleChangedProductType(...))
            ->onRemove(ProductTypes::CONFIG_PRODUCTTYPES_KEY . '.{uid}', $productTypeService->handleDeletedProductType(...));

        Event::on(Sites::class, Sites::EVENT_AFTER_DELETE_SITE, function(DeleteSiteEvent $event) use ($productTypeService) {
            if (!Craft::$app->getProjectConfig()->getIsApplyingExternalChanges()) {
                $productTypeService->pruneDeletedSite($event);
            }
        });

        $ordersService = $this->getOrders();
        $projectConfigService->onAdd(OrdersService::CONFIG_FIELDLAYOUT_KEY, $ordersService->handleChangedFieldLayout(...))
            ->onUpdate(OrdersService::CONFIG_FIELDLAYOUT_KEY, $ordersService->handleChangedFieldLayout(...))
            ->onRemove(OrdersService::CONFIG_FIELDLAYOUT_KEY, $ordersService->handleDeletedFieldLayout(...));

        $transfersService = $this->getTransfers();
        $projectConfigService->onAdd(TransfersService::CONFIG_FIELDLAYOUT_KEY, $transfersService->handleChangedFieldLayout(...))
            ->onUpdate(TransfersService::CONFIG_FIELDLAYOUT_KEY, $transfersService->handleChangedFieldLayout(...))
            ->onRemove(TransfersService::CONFIG_FIELDLAYOUT_KEY, $transfersService->handleDeletedFieldLayout(...));

        $orderStatusService = $this->getOrderStatuses();
        $projectConfigService->onAdd(OrderStatuses::CONFIG_STATUSES_KEY . '.{uid}', $orderStatusService->handleChangedOrderStatus(...))
            ->onUpdate(OrderStatuses::CONFIG_STATUSES_KEY . '.{uid}', $orderStatusService->handleChangedOrderStatus(...))
            ->onRemove(OrderStatuses::CONFIG_STATUSES_KEY . '.{uid}', $orderStatusService->handleDeletedOrderStatus(...));

        Event::on(Emails::class, Emails::EVENT_AFTER_DELETE_EMAIL, function(EmailEvent $event) use ($orderStatusService) {
            if (!Craft::$app->getProjectConfig()->getIsApplyingExternalChanges()) {
                $orderStatusService->pruneDeletedEmail($event);
            }
        });

        $lineItemStatusService = $this->getLineItemStatuses();
        $projectConfigService->onAdd(LineItemStatuses::CONFIG_STATUSES_KEY . '.{uid}', $lineItemStatusService->handleChangedLineItemStatus(...))
            ->onUpdate(LineItemStatuses::CONFIG_STATUSES_KEY . '.{uid}', $lineItemStatusService->handleChangedLineItemStatus(...))
            ->onRemove(LineItemStatuses::CONFIG_STATUSES_KEY . '.{uid}', $lineItemStatusService->handleArchivedLineItemStatus(...));

        $emailService = $this->getEmails();
        $projectConfigService->onAdd(Emails::CONFIG_EMAILS_KEY . '.{uid}', $emailService->handleChangedEmail(...))
            ->onUpdate(Emails::CONFIG_EMAILS_KEY . '.{uid}', $emailService->handleChangedEmail(...))
            ->onRemove(Emails::CONFIG_EMAILS_KEY . '.{uid}', $emailService->handleDeletedEmail(...));

        $storesService = $this->getStores();
        $projectConfigService->onAdd(Stores::CONFIG_STORES_KEY . '.{uid}', $storesService->handleChangedStore(...))
            ->onUpdate(Stores::CONFIG_STORES_KEY . '.{uid}', $storesService->handleChangedStore(...))
            ->onRemove(Stores::CONFIG_STORES_KEY . '.{uid}', $storesService->handleDeletedStore(...));

        $projectConfigService->onAdd(Stores::CONFIG_SITESTORES_KEY . '.{uid}', $storesService->handleChangedSiteStore(...))
            ->onUpdate(Stores::CONFIG_SITESTORES_KEY . '.{uid}', $storesService->handleChangedSiteStore(...))
            ->onRemove(Stores::CONFIG_SITESTORES_KEY . '.{uid}', $storesService->handleDeletedSiteStore(...));

        $pdfService = $this->getPdfs();
        $projectConfigService->onAdd(Pdfs::CONFIG_PDFS_KEY . '.{uid}', $pdfService->handleChangedPdf(...))
            ->onUpdate(Pdfs::CONFIG_PDFS_KEY . '.{uid}', $pdfService->handleChangedPdf(...))
            ->onRemove(Pdfs::CONFIG_PDFS_KEY . '.{uid}', $pdfService->handleDeletedPdf(...));

        Event::on(ProjectConfig::class, ProjectConfig::EVENT_REBUILD, static function(RebuildConfigEvent $event) {
            $event->config['commerce'] = ProjectConfigData::rebuildProjectConfig();
        });
    }

    /**
     * Register general event listeners
     */
    private function _registerCraftEventListeners(): void
    {
        // Guard against the case where the Plugin class is loaded during Craft installation due to a project config existing but commerce is not installed.
        // Also fixed in core but this is an extra guard: https://github.com/craftcms/cms/commit/369807d9b8da0ff0968e292591eee5f8924b57cc
        if (!$this->isInstalled) {
            return;
        }

        // TODO: UserQuery::EVENT_AFTER_POPULATE_ELEMENTS was removed in Craft 6.
        // Re-wire customer attachment (primaryBillingAddressId / primaryShippingAddressId)
        // to the new element-loading lifecycle when its equivalent lands.
        // Original logic preserved below in a no-op closure so the customer-attach
        // code is easy to port once the new hook exists.
        // Event::on(UserQuery::class, UserQuery::EVENT_AFTER_POPULATE_ELEMENTS, function(PopulateElementsEvent $event) {
        //     $users = $event->elements;
        //     $customerIds = ArrayHelper::getColumn($users, 'id');
        //
        //     if (empty($customerIds)) {
        //         return;
        //     }
        //
        //     $customers = new Query()
        //         ->select(['customerId', 'primaryBillingAddressId', 'primaryShippingAddressId'])
        //         ->from([Table::CUSTOMERS])
        //         ->where(['customerId' => $customerIds])
        //         ->all();
        //
        //     if (empty($customers)) {
        //         return;
        //     }
        //
        //     foreach ($customers as $customer) {
        //         /** @var User|CustomerBehavior|null $user */
        //         $user = ArrayHelper::firstWhere($users, 'id', $customer['customerId']);
        //         if (!$user) {
        //             continue;
        //         }
        //
        //         $user->setPrimaryBillingAddressId($customer['primaryBillingAddressId']);
        //         $user->setPrimaryShippingAddressId($customer['primaryShippingAddressId']);
        //     }
        // });

        // Commerce screen on the Edit User screen is now registered in src/Plugin.php

        Event::on(Purchasable::class, Elements::EVENT_BEFORE_RESTORE_ELEMENT, [$this->getPurchasables(), 'beforeRestorePurchasableHandler']);
    }

    /**
     * Register Commerce’s template variable.
     */
    private function _registerVariables(): void
    {
        // Legacy Yii2 CraftVariable (backward compat) — `craft.commerce`/`craft.orders`/etc. for
        // the new Twig variable system are registered via `NewCraftVariable::macro(...)` in
        // src/Plugin.php now.
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
            new Install()->addForeignKeys();
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
     * Add relatedToProducts and relatedToVariants arguments to element queries.
     *
     * The handlers for these arguments themselves are registered via
     * CraftCms\Commerce\Plugin's boot() using the new GqlArguments registry.
     */
    private function _registerRelatedToArguments(): void
    {
        Event::on(Gql::class, Gql::EVENT_REGISTER_GQL_QUERIES, static function(RegisterGqlQueriesEvent $event) {
            $relatedToProductsArg = [
                'name' => 'relatedToProducts',
                'type' => \GraphQL\Type\Definition\Type::listOf(ProductRelation::getType()),
                'description' => 'Narrows the query results to elements that relate to a product list defined with this argument.',
            ];
            $relatedToVariantsArg = [
                'name' => 'relatedToVariants',
                'type' => \GraphQL\Type\Definition\Type::listOf(VariantRelation::getType()),
                'description' => 'Narrows the query results to elements that relate to a variant list defined with this argument.',
            ];

            // Add the arguments to all relevant queries
            foreach ($event->queries as $queryName => &$queryConfig) {
                if (isset($queryConfig['args']) && is_array($queryConfig['args'])) {
                    $queryConfig['args']['relatedToProducts'] = $relatedToProductsArg;
                    $queryConfig['args']['relatedToVariants'] = $relatedToVariantsArg;
                }
            }
        });
    }

}
