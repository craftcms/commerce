<?php

namespace craft\commerce\migrations;

use craft\db\Migration;

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
            'PRIMARY KEY(siteId)',
        ]);

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
