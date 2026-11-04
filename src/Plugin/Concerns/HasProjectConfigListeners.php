<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Plugin\Concerns;

use CraftCms\Cms\ProjectConfig\Events\ProjectConfigRebuilt;
use CraftCms\Cms\ProjectConfig\ProjectConfig;
use CraftCms\Cms\Site\Events\SiteDeleted;
use CraftCms\Commerce\Email\Emails;
use CraftCms\Commerce\Email\Events\EmailDeleted;
use CraftCms\Commerce\Helpers\ProjectConfigData;
use CraftCms\Commerce\Order\LineItemStatuses;
use CraftCms\Commerce\Order\Orders;
use CraftCms\Commerce\Order\OrderStatuses;
use CraftCms\Commerce\Payment\Gateway\Gateways;
use CraftCms\Commerce\Pdf\Pdfs;
use CraftCms\Commerce\Product\ProductType\ProductTypes;
use CraftCms\Commerce\Store\Stores;
use CraftCms\Commerce\Transfer\Transfers;
use Illuminate\Support\Facades\Event;

/**
 * @mixin Plugin
 */
trait HasProjectConfigListeners
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
}
