<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\helpers;

use Craft;
use craft\commerce\db\Table;
use craft\commerce\elements\Order as OrderElement;
use craft\commerce\elements\Subscription;
use craft\commerce\models\Store;
use craft\commerce\Plugin;
use craft\db\Query;
use craft\helpers\Json;

/**
 * Class ProjectConfigData
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.1.3
 */
class ProjectConfigData
{
    /**
     * Return a rebuilt project config array
     */
    public static function rebuildProjectConfig(): array
    {
        $output = [];

        $output['emails'] = self::_getEmailData();
        $output['pdfs'] = self::_getPdfData();
        $output['gateways'] = self::_rebuildGatewayProjectConfig();
        $output['stores'] = self::_getStoresData();
        $output['siteStores'] = self::_getSiteStoresData();

        $orderFieldLayout = Craft::$app->getFields()->getLayoutByType(OrderElement::class);

        if ($orderFieldLayoutConfig = $orderFieldLayout->getConfig()) {
            $output['orders'] = [
                'fieldLayouts' => [
                    $orderFieldLayout->uid => $orderFieldLayoutConfig,
                ],
            ];
        }

        $output['orderStatuses'] = self::_getStatusData();
        $output['lineItemStatuses'] = self::_getLineItemStatusData();
        $output['productTypes'] = self::_getProductTypeData();

        $subscriptionFieldLayout = Craft::$app->getFields()->getLayoutByType(Subscription::class);

        if ($subscriptionFieldLayoutConfig = $subscriptionFieldLayout->getConfig()) {
            $output['subscriptions'] = [
                'fieldLayouts' => [
                    $subscriptionFieldLayout->uid => $subscriptionFieldLayoutConfig,
                ],
            ];
        }

        return array_filter($output);
    }

    /**
     * Return gateway data config array.
     */
    private static function _rebuildGatewayProjectConfig(): array
    {
        $gatewayData = (new Query())
            ->select(['*'])
            ->from([Table::GATEWAYS])
            ->where(['isArchived' => false])
            ->all();

        $configData = [];

        foreach ($gatewayData as $gatewayRow) {
            $settings = Json::decodeIfJson($gatewayRow['settings']);
            $configData[$gatewayRow['uid']] = [
                'name' => $gatewayRow['name'],
                'handle' => $gatewayRow['handle'],
                'type' => $gatewayRow['type'],
                'settings' => $settings,
                'sortOrder' => (int)$gatewayRow['sortOrder'],
                'paymentType' => $gatewayRow['paymentType'],
                'isFrontendEnabled' => (bool)$gatewayRow['isFrontendEnabled'],
            ];
        }


        return $configData;
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
        $productTypeRows = (new Query())
            ->select([
                'descriptionFormat',
                'fieldLayoutId',
                'handle',
                'hasDimensions',
                'hasProductTitleField',
                'maxVariants',
                'hasVariantTitleField',
                'name',
                'productTitleFormat',
                'skuFormat',
                'variantTitleFormat',
                'uid',
                'variantFieldLayoutId',
            ])
            ->from([Table::PRODUCTTYPES . ' productTypes'])
            ->all();

        $typeData = [];

        foreach ($productTypeRows as $productTypeRow) {
            $rowUid = $productTypeRow['uid'];

            if (!empty($productTypeRow['fieldLayoutId'])) {
                $layout = Craft::$app->getFields()->getLayoutById($productTypeRow['fieldLayoutId']);

                if ($layout && ($layoutConfig = $layout->getConfig())) {
                    $productTypeRow['productFieldLayouts'] = [
                        $layout->uid => $layoutConfig,
                    ];
                }
            }

            if (!empty($productTypeRow['variantFieldLayoutId'])) {
                $layout = Craft::$app->getFields()->getLayoutById($productTypeRow['variantFieldLayoutId']);

                if ($layout && ($layoutConfig = $layout->getConfig())) {
                    $productTypeRow['variantFieldLayouts'] = [
                        $layout->uid => $layoutConfig,
                    ];
                }
            }

            unset($productTypeRow['uid'], $productTypeRow['fieldLayoutId'], $productTypeRow['variantFieldLayoutId']);
            $productTypeRow['hasDimensions'] = (bool)$productTypeRow['hasDimensions'];
            $productTypeRow['hasVariantTitleField'] = (bool)$productTypeRow['hasVariantTitleField'];
            $productTypeRow['hasProductTitleField'] = (bool)$productTypeRow['hasProductTitleField'];

            $productTypeRow['siteSettings'] = [];
            $typeData[$rowUid] = $productTypeRow;
        }

        $productTypeSiteRows = (new Query())
            ->select([
                'producttypes.uid AS typeUid',
                'producttypes_sites.hasUrls',
                'producttypes_sites.template',
                'producttypes_sites.uriFormat',
                'sites.uid AS siteUid',
            ])
            ->from([Table::PRODUCTTYPES_SITES . ' producttypes_sites'])
            ->innerJoin('{{%sites}} sites', '[[sites.id]] = [[producttypes_sites.siteId]]')
            ->innerJoin(Table::PRODUCTTYPES . ' producttypes', '[[producttypes.id]] = [[producttypes_sites.productTypeId]]')
            ->all();

        foreach ($productTypeSiteRows as $productTypeSiteRow) {
            $typeUid = $productTypeSiteRow['typeUid'];
            $siteUid = $productTypeSiteRow['siteUid'];
            unset($productTypeSiteRow['siteUid'], $productTypeSiteRow['typeUid']);

            $productTypeSiteRow['hasUrls'] = (bool)$productTypeSiteRow['hasUrls'];

            $typeData[$typeUid]['siteSettings'][$siteUid] = $productTypeSiteRow;
        }

        return $typeData;
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
        $statusData = [];

        $statusRows = (new Query())
            ->select([
                'color',
                'default',
                'description',
                'handle',
                'id',
                'name',
                'sortOrder',
                'uid',
            ])
            ->indexBy('id')
            ->orderBy('sortOrder')
            ->from([Table::ORDERSTATUSES])
            ->all();

        foreach ($statusRows as &$statusRow) {
            unset($statusRow['id']);
            $statusRow['emails'] = [];
        }

        $relationRows = (new Query())
            ->select([
                'emailUid' => 'emails.uid',
                'statusId' => 'relations.orderStatusId',
            ])
            ->from([Table::ORDERSTATUS_EMAILS . ' relations'])
            ->leftJoin(Table::EMAILS . ' emails', '[[emails.id]] = [[relations.emailId]]')
            ->all();

        foreach ($relationRows as $relationRow) {
            $statusRows[$relationRow['statusId']]['emails'][$relationRow['emailUid']] = $relationRow['emailUid'];
        }

        foreach ($statusRows as &$statusRow) {
            $statusUid = $statusRow['uid'];
            unset($statusRow['uid']);

            $statusRow['default'] = (bool)$statusRow['default'];
            $statusRow['sortOrder'] = (int)$statusRow['sortOrder'];

            $statusData[$statusUid] = $statusRow;
        }

        return $statusData;
    }
}
