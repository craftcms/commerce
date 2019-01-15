<?php

namespace craft\commerce\migrations;

use Craft;
use craft\commerce\Plugin;
use craft\db\Migration;

/**
 * m190109_223402_set_edition migration.
 */
class m190109_223402_set_edition extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        // Don't make the same config changes twice
        $projectConfig = Craft::$app->getProjectConfig();
        $schemaVersion = $projectConfig->get('plugins.commerce.schemaVersion', true);
        if (version_compare($schemaVersion, '2.0.56', '>=')) {
            return;
        }

        Craft::$app->getPlugins()->switchEdition('commerce', Plugin::EDITION_PRO);
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m190109_223402_set_edition cannot be reverted.\n";
        return false;
    }
}
