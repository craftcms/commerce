<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\helpers;

use Craft;
use craft\commerce\db\Table;
use craft\commerce\elements\Subscription;
use craft\commerce\elements\Order as OrderElement;
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
    // Public Methods
    // =========================================================================


    // Project config rebuild methods
    // =========================================================================

    /**
     * Return a rebuilt project config array
     *
     * @return array
     */
    public static function rebuildProjectConfig(): array
    {
        $output = [];

        $output['emails'] = self::_getEmailData();
        $output['gateways'] = self::_rebuildGatewayProjectConfig();

        $orderFieldLayout = Craft::$app->getFields()->getLayoutByType(OrderElement::class);

        if ($orderFieldLayout->uid) {
            $output['orders'] = [
                'fieldLayouts' => [
                    $orderFieldLayout->uid => $orderFieldLayout->getConfig()
                ]
            ];
        }

        $output['orderStatuses'] = self::_getStatusData();
        $output['productTypes'] = self::_getProductTypeData();

        $subscriptionFieldLayout = Craft::$app->getFields()->getLayoutByType(Subscription::class);

        if ($subscriptionFieldLayout->uid) {
            $output['subscriptions'] = [
                'fieldLayouts' => [
                    $subscriptionFieldLayout->uid => $subscriptionFieldLayout->getConfig()
                ]
            ];
        }


        return $output;
    }

    /**
     * Return gateway data config array.
     *
     * @return array
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
     * Return product type data config array.
     *
     * @return array
     */
    private static function _getProductTypeData(): array
    {
        $productTypeRows = (new Query())
            ->select([
                'fieldLayoutId',
                'variantFieldLayoutId',
                'name',
                'handle',
                'hasDimensions',
                'hasVariants',
                'hasVariantTitleField',
                'titleFormat',
                'skuFormat',
                'descriptionFormat',
                'uid'
            ])
            ->from([Table::PRODUCTTYPES . ' productTypes'])
            ->all();

        $typeData = [];

        foreach ($productTypeRows as $productTypeRow) {
            $rowUid = $productTypeRow['uid'];

            if (!empty($productTypeRow['fieldLayoutId'])) {
                $layout = Craft::$app->getFields()->getLayoutById($productTypeRow['fieldLayoutId']);

                if ($layout) {
                    $productTypeRow['productFieldLayouts'] = [$layout->uid => $layout->getConfig()];
                }
            }

            if (!empty($productTypeRow['variantFieldLayoutId'])) {
                $layout = Craft::$app->getFields()->getLayoutById($productTypeRow['variantFieldLayoutId']);

                if ($layout) {
                    $productTypeRow['variantFieldLayouts'] = [$layout->uid => $layout->getConfig()];
                }
            }

            unset($productTypeRow['uid'], $productTypeRow['fieldLayoutId'], $productTypeRow['variantFieldLayoutId']);
            $productTypeRow['hasDimensions'] = (bool)$productTypeRow['hasDimensions'];
            $productTypeRow['hasVariants'] = (bool)$productTypeRow['hasVariants'];
            $productTypeRow['hasVariantTitleField'] = (bool)$productTypeRow['hasVariantTitleField'];

            $productTypeRow['siteSettings'] = [];
            $typeData[$rowUid] = $productTypeRow;
        }

        $productTypeSiteRows = (new Query())
            ->select([
                'producttypes_sites.hasUrls',
                'producttypes_sites.uriFormat',
                'producttypes_sites.template',
                'sites.uid AS siteUid',
                'producttypes.uid AS typeUid',
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
     *
     * @return array
     */
    private static function _getEmailData(): array
    {
        $emailRows = (new Query())
            ->select([
                'emails.uid',
                'emails.name',
                'emails.subject',
                'emails.recipientType',
                'emails.to',
                'emails.bcc',
                'emails.enabled',
                'emails.templatePath',
                'emails.attachPdf',
                'emails.pdfTemplatePath'
            ])
            ->orderBy('name')
            ->from([Table::EMAILS . ' emails'])
            ->indexBy('uid')
            ->all();

        foreach ($emailRows as &$row) {
            unset($row['uid']);

            $row['enabled'] = (bool)$row['enabled'];
            $row['attachPdf'] = (bool)$row['attachPdf'];
        }

        return $emailRows;
    }

    /**
     * Return order status data config array.
     *
     * @return array
     */
    private static function _getStatusData(): array
    {
        $statusData = [];

        $statusRows = (new Query())
            ->select([
                'id',
                'uid',
                'name',
                'handle',
                'color',
                'sortOrder',
                'default',
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
                'relations.orderStatusId AS statusId',
                'emails.uid AS emailUid',
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
