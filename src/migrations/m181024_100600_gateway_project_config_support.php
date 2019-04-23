<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\helpers\Json;

/**
 * m181024_100600_gateway_project_config_support migration.
 */
class m181024_100600_gateway_project_config_support extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        // Don't make the same config changes twice
        $projectConfig = Craft::$app->getProjectConfig();
        $schemaVersion = $projectConfig->get('plugins.commerce.schemaVersion', true);
        if (version_compare($schemaVersion, '2.0.52', '>')) {
            return;
        }

        $gatewayData = (new Query())
            ->select(['*'])
            ->from(['{{%commerce_gateways}}'])
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

        $projectConfig->set('commerce.gateways', $configData);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m181024_100600_gateway_project_config_support cannot be reverted.\n";
        return false;
    }
}
