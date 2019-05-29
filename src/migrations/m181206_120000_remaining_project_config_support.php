<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use Craft;
use craft\commerce\elements\Subscription;
use craft\commerce\services\Emails;
use craft\commerce\services\Orders;
use craft\commerce\services\OrderStatuses;
use craft\commerce\services\ProductTypes;
use craft\commerce\services\Subscriptions;
use craft\db\Migration;
use craft\db\Query;
use craft\helpers\StringHelper;

/**
 * m181206_120000_remaining_project_config_support migration.
 */
class m181206_120000_remaining_project_config_support extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        // Don't make the same config changes twice
        $projectConfig = Craft::$app->getProjectConfig();
        $schemaVersion = $projectConfig->get('plugins.commerce.schemaVersion', true);
        if (version_compare($schemaVersion, '2.0.55', '>')) {
            return true;
        }

        $id = (new Query())->select(['fieldLayoutId'])->from(['{{%commerce_ordersettings}}'])->scalar();
        // Field layouts
        $orderFieldLayout = Craft::$app->getFields()->getLayoutById($id);
        Craft::$app->getFields()->deleteLayoutById($id);

        if ($orderFieldLayout && $layoutConfig = $orderFieldLayout->getConfig()) {
            $orderConfigData = [StringHelper::UUID() => $layoutConfig];
            $projectConfig->set(Orders::CONFIG_FIELDLAYOUT_KEY, $orderConfigData);
        }

        $subscriptionFieldLayout = Craft::$app->getFields()->getLayoutByType(Subscription::class);
        Craft::$app->getFields()->deleteLayoutsByType(Subscription::class);
        $layoutConfig = $subscriptionFieldLayout->getConfig();

        if ($layoutConfig) {
            $subscriptionConfigData = [StringHelper::UUID() => $layoutConfig];
            $projectConfig->set(Subscriptions::CONFIG_FIELDLAYOUT_KEY, $subscriptionConfigData);
        }

        $productTypeData = $this->_getProductTypeData();
        $projectConfig->set(ProductTypes::CONFIG_PRODUCTTYPES_KEY, $productTypeData);

        $emailData = $this->_getEmailData();
        $projectConfig->set(Emails::CONFIG_EMAILS_KEY, $emailData);

        $statusData = $this->_getStatusData();
        $projectConfig->set(OrderStatuses::CONFIG_STATUSES_KEY, $statusData);

        $this->dropTableIfExists('{{%commerce_ordersettings}}');

        return true;
    }

    /**
     * Return product type data config array.
     *
     * @return array
     */
    private function _getProductTypeData(): array
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
            ->from(['{{%commerce_producttypes}} productTypes'])
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
            ->from(['{{%commerce_producttypes_sites}} producttypes_sites'])
            ->innerJoin('{{%sites}} sites', '[[sites.id]] = [[producttypes_sites.siteId]]')
            ->innerJoin('{{%commerce_producttypes}} producttypes', '[[producttypes.id]] = [[producttypes_sites.productTypeId]]')
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
    private function _getEmailData(): array
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
            ->from(['{{%commerce_emails}} emails'])
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
    private function _getStatusData(): array
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
            ->where(['isArchived' => false])
            ->indexBy('id')
            ->orderBy('sortOrder')
            ->from(['{{%commerce_orderstatuses}}'])
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
            ->from(['{{%commerce_orderstatus_emails}} relations'])
            ->leftJoin('{{%commerce_emails}} emails', '[[emails.id]] = [[relations.emailId]]')
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

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m181206_120000_remaining_project_config_support cannot be reverted.\n";
        return false;
    }
}
