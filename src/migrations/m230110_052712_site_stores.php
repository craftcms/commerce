<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\commerce\services\Stores;
use craft\db\Migration;
use craft\db\Query;

/**
 * m230110_052712_sites_settings migration.
 */
class m230110_052712_site_stores extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Create commerce_sites_settings table
        $this->createTable('{{%commerce_site_stores}}', [
            'siteId' => $this->integer(),
            'storeId' => $this->integer()->null(), // defaults to primary store in app
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
            'PRIMARY KEY([[siteId]])',
        ]);

        // Get the primary store
        $primaryStore = (new Query())
            ->select(['id', 'uid'])
            ->from([Table::STORES])
            ->where(['primary' => true])
            ->one();

        // Get all sites
        $sites = (new Query())
            ->select(['id', 'handle', 'uid'])
            ->from(\craft\db\Table::SITES)
            ->where(['dateDeleted' => null])
            ->all();

        // Create site stores records
        foreach ($sites as $site) {
            $this->insert('{{%commerce_site_stores}}', [
                'siteId' => $site['id'],
                'storeId' => $primaryStore['id'],
                'uid' => $site['uid'],
            ]);

            $projectConfig = \Craft::$app->getProjectConfig();

            $configPath = Stores::CONFIG_SITESTORES_KEY . "." . $site['uid'];
            $projectConfig->set(
                $configPath,
                // Mirror what the site store model `getConfig()` method returns
                ['store' => $primaryStore['uid']],
                "Save the “{$site['handle']}” commerce site store mapping"
            );
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m230110_052712_site_stores cannot be reverted.\n";
        return false;
    }
}
