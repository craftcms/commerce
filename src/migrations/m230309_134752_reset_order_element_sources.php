<?php

namespace craft\commerce\migrations;

use Craft;
use craft\commerce\elements\Order;
use craft\db\Migration;
use craft\services\ProjectConfig;

/**
 * m230309_134752_reset_order_element_sources migration.
 */
class m230309_134752_reset_order_element_sources extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $projectConfig = Craft::$app->getProjectConfig();
        $schemaVersion = $projectConfig->get('plugins.commerce.schemaVersion', true);

        if (version_compare($schemaVersion, '5.0.28', '<')) {
            $muteEvents = $projectConfig->muteEvents;
            $projectConfig->muteEvents = true;

            $projectConfig->remove(ProjectConfig::PATH_ELEMENT_SOURCES . '.' . Order::class, 'Reset order element sources');

            $projectConfig->muteEvents = $muteEvents;
        }


        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m230309_134752_reset_order_element_sources cannot be reverted.\n";
        return false;
    }
}
