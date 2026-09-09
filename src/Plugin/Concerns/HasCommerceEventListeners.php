<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Plugin\Concerns;

use craft\base\Event as YiiEvent;
use craft\ckeditor\events\DefineLinkOptionsEvent;
use craft\ckeditor\Field as CKEditorField;
use craft\commerce\services\Carts as LegacyCarts;
use craft\commerce\services\Customers as LegacyCustomers;
use craft\commerce\services\Emails as LegacyEmails;
use craft\commerce\services\Inventory as LegacyInventory;
use craft\commerce\services\LineItems as LegacyLineItems;
use craft\commerce\services\LineItemStatuses as LegacyLineItemStatuses;
use craft\commerce\services\OrderHistories as LegacyOrderHistories;
use craft\commerce\services\OrderStatuses as LegacyOrderStatuses;
use craft\commerce\services\PaymentCurrencies as LegacyPaymentCurrencies;
use craft\commerce\services\Payments as LegacyPayments;
use craft\commerce\services\PaymentSources as LegacyPaymentSources;
use craft\commerce\services\Pdfs as LegacyPdfs;
use craft\commerce\services\ProductTypes as LegacyProductTypes;
use craft\commerce\services\Purchasables as LegacyPurchasables;
use craft\commerce\services\ShippingMethods as LegacyShippingMethods;
use craft\commerce\services\Stores as LegacyStores;
use craft\commerce\services\Taxes as LegacyTaxes;
use craft\commerce\services\Transactions as LegacyTransactions;
use craft\commerce\services\Webhooks as LegacyWebhooks;
use craft\events\RegisterUrlRulesEvent;
use craft\fixfks\controllers\RestoreController as RestoreFksController;
use craft\web\UrlManager;
use CraftCms\Cms\Gql\Events\GqlArgumentsResolving;
use CraftCms\Cms\ProjectConfig\Events\ProjectConfigRebuilt;
use CraftCms\Cms\ProjectConfig\ProjectConfig;
use CraftCms\Cms\Site\Events\SiteDeleted;
use CraftCms\Cms\Support\Facades\Plugins;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Commerce\Email\Emails;
use CraftCms\Commerce\Email\Events\EmailDeleted;
use CraftCms\Commerce\Gql\Types\Input\Criteria\ProductRelation;
use CraftCms\Commerce\Gql\Types\Input\Criteria\VariantRelation;
use CraftCms\Commerce\Helpers\ProjectConfigData;
use CraftCms\Commerce\Order\LineItemStatuses;
use CraftCms\Commerce\Order\Orders;
use CraftCms\Commerce\Order\OrderStatuses;
use CraftCms\Commerce\Payment\Gateway\Gateways;
use CraftCms\Commerce\Pdf\Pdfs;
use CraftCms\Commerce\Plugin\Plugin;
use CraftCms\Commerce\Product\Elements\Product;
use CraftCms\Commerce\Product\ProductType\ProductTypes;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use CraftCms\Commerce\Store\Stores;
use CraftCms\Commerce\Transfer\Transfers;
use GraphQL\Type\Definition\Type;
use Illuminate\Support\Facades\Event;

use function CraftCms\Cms\t;

/**
 * @mixin Plugin
 */
trait HasCommerceEventListeners
{
    /**
     * Registers Commerce's project config event listeners.
     */
    private function registerProjectConfigEventListeners(): void
    {
        $projectConfig = app(ProjectConfig::class);

        $projectConfig->onAdd(Gateways::CONFIG_GATEWAY_KEY . '.{uid}', app(Gateways::class)->handleChangedGateway(...))
            ->onUpdate(Gateways::CONFIG_GATEWAY_KEY . '.{uid}', app(Gateways::class)->handleChangedGateway(...))
            ->onRemove(Gateways::CONFIG_GATEWAY_KEY . '.{uid}', app(Gateways::class)->handleArchivedGateway(...));

        $projectConfig->onAdd(ProductTypes::CONFIG_PRODUCTTYPES_KEY . '.{uid}', app(ProductTypes::class)->handleChangedProductType(...))
            ->onUpdate(ProductTypes::CONFIG_PRODUCTTYPES_KEY . '.{uid}', app(ProductTypes::class)->handleChangedProductType(...))
            ->onRemove(ProductTypes::CONFIG_PRODUCTTYPES_KEY . '.{uid}', app(ProductTypes::class)->handleDeletedProductType(...));

        Event::listen(SiteDeleted::class, static function(SiteDeleted $event) {
            if (!app(ProjectConfig::class)->isApplyingExternalChanges) {
                app(ProductTypes::class)->pruneDeletedSite($event);
            }
        });

        $projectConfig->onAdd(Orders::CONFIG_FIELDLAYOUT_KEY, app(Orders::class)->handleChangedFieldLayout(...))
            ->onUpdate(Orders::CONFIG_FIELDLAYOUT_KEY, app(Orders::class)->handleChangedFieldLayout(...))
            ->onRemove(Orders::CONFIG_FIELDLAYOUT_KEY, app(Orders::class)->handleDeletedFieldLayout(...));

        $projectConfig->onAdd(Transfers::CONFIG_FIELDLAYOUT_KEY, app(Transfers::class)->handleChangedFieldLayout(...))
            ->onUpdate(Transfers::CONFIG_FIELDLAYOUT_KEY, app(Transfers::class)->handleChangedFieldLayout(...))
            ->onRemove(Transfers::CONFIG_FIELDLAYOUT_KEY, app(Transfers::class)->handleDeletedFieldLayout(...));

        $projectConfig->onAdd(OrderStatuses::CONFIG_STATUSES_KEY . '.{uid}', app(OrderStatuses::class)->handleChangedOrderStatus(...))
            ->onUpdate(OrderStatuses::CONFIG_STATUSES_KEY . '.{uid}', app(OrderStatuses::class)->handleChangedOrderStatus(...))
            ->onRemove(OrderStatuses::CONFIG_STATUSES_KEY . '.{uid}', app(OrderStatuses::class)->handleDeletedOrderStatus(...));

        Event::listen(EmailDeleted::class, static function(EmailDeleted $event) {
            if (!app(ProjectConfig::class)->isApplyingExternalChanges) {
                app(OrderStatuses::class)->pruneDeletedEmail($event);
            }
        });

        $projectConfig->onAdd(LineItemStatuses::CONFIG_STATUSES_KEY . '.{uid}', app(LineItemStatuses::class)->handleChangedLineItemStatus(...))
            ->onUpdate(LineItemStatuses::CONFIG_STATUSES_KEY . '.{uid}', app(LineItemStatuses::class)->handleChangedLineItemStatus(...))
            ->onRemove(LineItemStatuses::CONFIG_STATUSES_KEY . '.{uid}', app(LineItemStatuses::class)->handleArchivedLineItemStatus(...));

        $projectConfig->onAdd(Emails::CONFIG_EMAILS_KEY . '.{uid}', app(Emails::class)->handleChangedEmail(...))
            ->onUpdate(Emails::CONFIG_EMAILS_KEY . '.{uid}', app(Emails::class)->handleChangedEmail(...))
            ->onRemove(Emails::CONFIG_EMAILS_KEY . '.{uid}', app(Emails::class)->handleDeletedEmail(...));

        $projectConfig->onAdd(Stores::CONFIG_STORES_KEY . '.{uid}', app(Stores::class)->handleChangedStore(...))
            ->onUpdate(Stores::CONFIG_STORES_KEY . '.{uid}', app(Stores::class)->handleChangedStore(...))
            ->onRemove(Stores::CONFIG_STORES_KEY . '.{uid}', app(Stores::class)->handleDeletedStore(...));

        $projectConfig->onAdd(Stores::CONFIG_SITESTORES_KEY . '.{uid}', app(Stores::class)->handleChangedSiteStore(...))
            ->onUpdate(Stores::CONFIG_SITESTORES_KEY . '.{uid}', app(Stores::class)->handleChangedSiteStore(...))
            ->onRemove(Stores::CONFIG_SITESTORES_KEY . '.{uid}', app(Stores::class)->handleDeletedSiteStore(...));

        $projectConfig->onAdd(Pdfs::CONFIG_PDFS_KEY . '.{uid}', app(Pdfs::class)->handleChangedPdf(...))
            ->onUpdate(Pdfs::CONFIG_PDFS_KEY . '.{uid}', app(Pdfs::class)->handleChangedPdf(...))
            ->onRemove(Pdfs::CONFIG_PDFS_KEY . '.{uid}', app(Pdfs::class)->handleDeletedPdf(...));

        Event::listen(ProjectConfigRebuilt::class, static function(ProjectConfigRebuilt $event) {
            $event->config['commerce'] = ProjectConfigData::rebuildProjectConfig();
        });
    }

    private function registerCKEditorLinkOptions(): void
    {
        if (!class_exists(CKEditorField::class)) {
            return;
        }

        $ckEditorPlugin = Plugins::getPlugin('ckeditor');
        if (!$ckEditorPlugin || version_compare($ckEditorPlugin->version, '3.0', '<')) {
            return;
        }

        /** @phpstan-ignore-next-line argument.type, class.notFound (craft\ckeditor\Field/DefineLinkOptionsEvent belong to the optional, third-party craftcms/ckeditor plugin, not a commerce dependency — unresolvable to static analysis when it isn't installed) */
        YiiEvent::on(CKEditorField::class, CKEditorField::EVENT_DEFINE_LINK_OPTIONS, static function(DefineLinkOptionsEvent $event) {
            // Include a Product link option if there are any product types that have URLs
            $productSources = [];

            $sites = Sites::getAllSites();

            foreach (app(ProductTypes::class)->getAllProductTypes() as $productType) {
                foreach ($sites as $site) {
                    $productTypeSettings = $productType->getSiteSettings();
                    if (isset($productTypeSettings[$site->id]) && $productTypeSettings[$site->id]->hasUrls) {
                        $productSources[] = 'productType:' . $productType->uid;
                    }
                }
            }

            $productSources = array_unique($productSources);

            if ($productSources) {
                /** @phpstan-ignore-next-line class.notFound (see note on the Event::on() registration above) */
                $event->linkOptions[] = [
                    'label' => t('Link to a product', category: 'commerce'),
                    'elementType' => Product::class,
                    'refHandle' => Product::refHandle(),
                    'sources' => $productSources,
                ];

                /** @phpstan-ignore-next-line class.notFound (see note on the Event::on() registration above) */
                $event->linkOptions[] = [
                    'label' => t('Link to a variant', category: 'commerce'),
                    'elementType' => Variant::class,
                    'refHandle' => Variant::refHandle(),
                    'sources' => $productSources,
                ];
            }
        });
    }

    private function registerGqlRelatedToArguments(): void
    {
        Event::listen(GqlArgumentsResolving::class, static function(GqlArgumentsResolving $event) {
            $event->arguments['relatedToProducts'] = [
                'name' => 'relatedToProducts',
                'type' => Type::listOf(ProductRelation::getType()),
                'description' => 'Narrows the query results to elements that relate to a product list defined with this argument.',
            ];
            $event->arguments['relatedToVariants'] = [
                'name' => 'relatedToVariants',
                'type' => Type::listOf(VariantRelation::getType()),
                'description' => 'Narrows the query results to elements that relate to a variant list defined with this argument.',
            ];
        });
    }

    private function registerForeignKeysRestore(): void
    {
        if (!class_exists(RestoreFksController::class)) {
            return;
        }

        /** @phpstan-ignore-next-line argument.type (class_exists() above guarantees this is a yii\base\Component subclass) */
        YiiEvent::on(RestoreFksController::class, RestoreFksController::EVENT_AFTER_RESTORE_FKS, function() {
            $this->createInstallMigration()?->addForeignKeys();
        });
    }

    private function registerLegacyCpRoutes(): void
    {
        YiiEvent::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES, static function(RegisterUrlRulesEvent $event) {
            $event->rules['commerce'] = ['template' => 'commerce/index'];
        });
    }

    /**
     * Bridges new Laravel events to their legacy `craft\commerce\services\*` Yii2 counterparts,
     * matching the pattern used by the CMS yii2-adapter (e.g. `craft\services\Volumes::registerEvents()`).
     */
    private function registerLegacyEventBridges(): void
    {
        LegacyCarts::registerEvents();
        LegacyCustomers::registerEvents();
        LegacyEmails::registerEvents();
        LegacyInventory::registerEvents();
        LegacyLineItems::registerEvents();
        LegacyLineItemStatuses::registerEvents();
        LegacyOrderHistories::registerEvents();
        LegacyOrderStatuses::registerEvents();
        LegacyPaymentCurrencies::registerEvents();
        LegacyPayments::registerEvents();
        LegacyPaymentSources::registerEvents();
        LegacyPdfs::registerEvents();
        LegacyProductTypes::registerEvents();
        LegacyPurchasables::registerEvents();
        LegacyShippingMethods::registerEvents();
        LegacyStores::registerEvents();
        LegacyTaxes::registerEvents();
        LegacyTransactions::registerEvents();
        LegacyWebhooks::registerEvents();
    }
}
