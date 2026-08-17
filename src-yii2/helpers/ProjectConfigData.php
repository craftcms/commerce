<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\helpers;

use Craft;
use craft\commerce\elements\Order as OrderElement;
use craft\commerce\models\Store;
use craft\commerce\Plugin;
use craft\commerce\services\Emails;
use craft\commerce\services\Gateways;
use craft\commerce\services\LineItemStatuses;
use craft\commerce\services\OrderStatuses;
use craft\commerce\services\Pdfs;
use craft\commerce\services\ProductTypes;
use craft\commerce\services\Stores;

/**
 * Class ProjectConfigData
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.1.3
 */
class ProjectConfigData
{
    /**
     * @var bool
     */
    private static $_processedStores = false;

    /**
     * Ensure all stores are processed.
     *
     * @param bool $force
     * @since 5.0.3
     */
    public static function ensureAllStoresProcessed(bool $force = false): void
    {
        $projectConfig = Craft::$app->getProjectConfig();

        if (self::$_processedStores || (!$force && !$projectConfig->getIsApplyingExternalChanges())) {
            return;
        }

        self::$_processedStores = true;

        $allStores = $projectConfig->get(Stores::CONFIG_STORES_KEY, true) ?? [];

        foreach ($allStores as $uid => $storeData) {
            // Ensure store is processed
            $projectConfig->processConfigChanges(Stores::CONFIG_STORES_KEY . '.' . $uid, $force);
        }
    }

    /**
     * Return a rebuilt project config array
     */
    public static function rebuildProjectConfig(): array
    {
        $output = [];

        $output[self::_getProjectConfigKey(Emails::CONFIG_EMAILS_KEY)] = self::_getEmailData();
        $output[self::_getProjectConfigKey(Pdfs::CONFIG_PDFS_KEY)] = self::_getPdfData();
        $output[self::_getProjectConfigKey(Gateways::CONFIG_GATEWAY_KEY)] = self::_rebuildGatewayProjectConfig();
        $output[self::_getProjectConfigKey(Stores::CONFIG_STORES_KEY)] = self::_getStoresData();
        $output[self::_getProjectConfigKey(Stores::CONFIG_SITESTORES_KEY)] = self::_getSiteStoresData();

        $orderFieldLayout = Craft::$app->getFields()->getLayoutByType(OrderElement::class);

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

    /**
     * @param string $key
     * @return string
     * @since 5.0.0
     */
    private static function _getProjectConfigKey(string $key): string
    {
        $configKeyPrefix = 'commerce.';
        return substr($key, strlen($configKeyPrefix));
    }

    /**
     * Return gateway data config array.
     */
    private static function _rebuildGatewayProjectConfig(): array
    {
        $data = [];
        foreach (Plugin::getInstance()->getGateways()->getAllGateways() as $gateway) {
            $data[$gateway->uid] = $gateway->getConfig();
        }
        return $data;
    }

    /**
     * Return stores data config array.
     */
    private static function _getStoresData(): array
    {
        $data = [];
        foreach (Plugin::getInstance()->getStores()->getAllStores() as $store) {
            $data[$store->uid] = $store->getConfig();
        }
        return $data;
    }

    private static function _getSiteStoresData(): array
    {
        $data = [];
        foreach (Plugin::getInstance()->getStores()->getAllSiteStores() as $siteStore) {
            $data[$siteStore->uid] = $siteStore->getConfig();
        }
        return $data;
    }

    /**
     * Return product type data config array.
     */
    private static function _getProductTypeData(): array
    {
        $data = [];
        foreach (Plugin::getInstance()->getProductTypes()->getAllProductTypes() as $productType) {
            $data[$productType->uid] = $productType->getConfig();
        }

        return $data;
    }

    /**
     * Return email data config array.
     */
    private static function _getEmailData(): array
    {
        $data = [];
        Plugin::getInstance()->getStores()->getAllStores()->each(function(Store $store) use (&$data) {
            foreach (Plugin::getInstance()->getEmails()->getAllEmails($store->id) as $email) {
                $data[$email->uid] = $email->getConfig();
            }
        });
        return $data;
    }

    /**
     * Return PDF data config array.
     */
    private static function _getPdfData(): array
    {
        $data = [];
        Plugin::getInstance()->getStores()->getAllStores()->each(function(Store $store) use (&$data) {
            foreach (Plugin::getInstance()->getPdfs()->getAllPdfs($store->id) as $pdf) {
                $data[$pdf->uid] = $pdf->getConfig();
            }
        });
        return $data;
    }

    /**
     * Return line item status data config array.
     */
    private static function _getLineItemStatusData(): array
    {
        $data = [];
        Plugin::getInstance()->getStores()->getAllStores()->each(function(Store $store) use (&$data) {
            foreach (Plugin::getInstance()->getLineItemStatuses()->getAllLineItemStatuses($store->id) as $status) {
                $data[$status->uid] = $status->getConfig();
            }
        });
        return $data;
    }

    /**
     * Return order status data config array.
     */
    private static function _getStatusData(): array
    {
        $data = [];
        Plugin::getInstance()->getStores()->getAllStores()->each(function(Store $store) use (&$data) {
            foreach (Plugin::getInstance()->getOrderStatuses()->getAllOrderStatuses($store->id) as $status) {
                $data[$status->uid] = $status->getConfig();
            }
        });

        return $data;
    }
}
