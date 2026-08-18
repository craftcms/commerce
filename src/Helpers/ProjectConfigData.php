<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Helpers;

use craft\commerce\elements\Order as OrderElement;
use craft\commerce\models\Store;
use CraftCms\Cms\Support\Facades\Fields;
use CraftCms\Cms\Support\Facades\ProjectConfig;
use CraftCms\Commerce\Catalog\ProductType\ProductTypes;
use CraftCms\Commerce\Email\Emails;
use CraftCms\Commerce\Order\LineItemStatuses;
use CraftCms\Commerce\Order\OrderStatuses;
use CraftCms\Commerce\Payment\Gateway\Gateways;
use CraftCms\Commerce\Pdf\Pdfs;
use CraftCms\Commerce\Store\Stores;

class ProjectConfigData
{
    private static bool $_processedStores = false;

    public static function ensureAllStoresProcessed(bool $force = false): void
    {
        if (self::$_processedStores || (!$force && !ProjectConfig::isApplyingExternalChanges())) {
            return;
        }

        self::$_processedStores = true;

        $allStores = ProjectConfig::get(Stores::CONFIG_STORES_KEY, true) ?? [];

        foreach ($allStores as $uid => $storeData) {
            ProjectConfig::processConfigChanges(Stores::CONFIG_STORES_KEY . '.' . $uid, $force);
        }
    }

    public static function rebuildProjectConfig(): array
    {
        $output = [];

        $output[self::_getProjectConfigKey(Emails::CONFIG_EMAILS_KEY)] = self::_getEmailData();
        $output[self::_getProjectConfigKey(Pdfs::CONFIG_PDFS_KEY)] = self::_getPdfData();
        $output[self::_getProjectConfigKey(Gateways::CONFIG_GATEWAY_KEY)] = self::_rebuildGatewayProjectConfig();
        $output[self::_getProjectConfigKey(Stores::CONFIG_STORES_KEY)] = self::_getStoresData();
        $output[self::_getProjectConfigKey(Stores::CONFIG_SITESTORES_KEY)] = self::_getSiteStoresData();

        $orderFieldLayout = Fields::getLayoutByType(OrderElement::class);

        if ($orderFieldLayoutConfig = $orderFieldLayout->getConfig()) {
            $output['orders'] = [
                'fieldLayouts' => [
                    $orderFieldLayout->uid => $orderFieldLayoutConfig,
                ],
            ];
        }

        $output[self::_getProjectConfigKey(OrderStatuses::CONFIG_STATUSES_KEY)] = self::_getStatusData();
        $output[self::_getProjectConfigKey(LineItemStatuses::CONFIG_STATUSES_KEY)] = self::_getLineItemStatusData();
        $output[self::_getProjectConfigKey(ProductTypes::CONFIG_PRODUCTTYPES_KEY)] = self::_getProductTypeData();

        return array_filter($output);
    }

    private static function _getProjectConfigKey(string $key): string
    {
        return substr($key, strlen('commerce.'));
    }

    private static function _rebuildGatewayProjectConfig(): array
    {
        $data = [];
        foreach (app(Gateways::class)->getAllGateways() as $gateway) {
            $data[$gateway->uid] = $gateway->getConfig();
        }
        return $data;
    }

    private static function _getStoresData(): array
    {
        $data = [];
        foreach (app(Stores::class)->getAllStores() as $store) {
            $data[$store->uid] = $store->getConfig();
        }
        return $data;
    }

    private static function _getSiteStoresData(): array
    {
        $data = [];
        foreach (app(Stores::class)->getAllSiteStores() as $siteStore) {
            $data[$siteStore->uid] = $siteStore->getConfig();
        }
        return $data;
    }

    private static function _getProductTypeData(): array
    {
        $data = [];
        foreach (app(ProductTypes::class)->getAllProductTypes() as $productType) {
            $data[$productType->uid] = $productType->getConfig();
        }
        return $data;
    }

    private static function _getEmailData(): array
    {
        $data = [];
        app(Stores::class)->getAllStores()->each(function(Store $store) use (&$data) {
            foreach (app(Emails::class)->getAllEmails($store->id) as $email) {
                $data[$email->uid] = $email->getConfig();
            }
        });
        return $data;
    }

    private static function _getPdfData(): array
    {
        $data = [];
        app(Stores::class)->getAllStores()->each(function(Store $store) use (&$data) {
            foreach (app(Pdfs::class)->getAllPdfs($store->id) as $pdf) {
                $data[$pdf->uid] = $pdf->getConfig();
            }
        });
        return $data;
    }

    private static function _getLineItemStatusData(): array
    {
        $data = [];
        app(Stores::class)->getAllStores()->each(function(Store $store) use (&$data) {
            foreach (app(LineItemStatuses::class)->getAllLineItemStatuses($store->id) as $status) {
                $data[$status->uid] = $status->getConfig();
            }
        });
        return $data;
    }

    private static function _getStatusData(): array
    {
        $data = [];
        app(Stores::class)->getAllStores()->each(function(Store $store) use (&$data) {
            foreach (app(OrderStatuses::class)->getAllOrderStatuses($store->id) as $status) {
                $data[$status->uid] = $status->getConfig();
            }
        });
        return $data;
    }
}
