<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Plugin\Concerns;

use craft\base\Event as YiiEvent;
use craft\ckeditor\events\DefineLinkOptionsEvent;
use craft\ckeditor\Field as CKEditorField;
use craft\commerce\services\Carts as LegacyCarts;
use craft\commerce\services\Customers as LegacyCustomers;
use craft\commerce\services\Emails as LegacyEmails;
use craft\commerce\services\Gateways as LegacyGateways;
use craft\commerce\services\Inventory as LegacyInventory;
use craft\commerce\services\LineItems as LegacyLineItems;
use craft\commerce\services\LineItemStatuses as LegacyLineItemStatuses;
use craft\commerce\services\OrderAdjustments as LegacyOrderAdjustments;
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
use craft\fixfks\controllers\RestoreController as RestoreFksController;
use CraftCms\Cms\Cms;
use CraftCms\Cms\Support\Facades\Plugins;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Commerce\Product\Elements\Product;
use CraftCms\Commerce\Product\ProductType\ProductTypes;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use Illuminate\Support\Facades\Event;

use function CraftCms\Cms\t;

/**
 * @mixin Plugin
 */
trait HasLegacyBridges
{
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

    /**
     * Reconciles our type registries against any legacy `Event::on(...)` listeners for the
     * deprecated EVENT_REGISTER_* constants, once every plugin has finished registering its listeners.
     */
    private function finalizeLegacyRegistrationEvents(): void
    {
        $this->app->booted(function() {
            if (!Cms::isInstalled(strict: true)) {
                return;
            }

            LegacyOrderAdjustments::finalizeRegistrationEvents();
            LegacyGateways::finalizeRegistrationEvents();
            LegacyPurchasables::finalizeRegistrationEvents();
        });
    }
}
