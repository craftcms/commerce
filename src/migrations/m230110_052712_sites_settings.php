<?php

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m230110_052712_sites_settings migration.
 */
class m230110_052712_sites_settings extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Create commerce_sites_settings table
        $this->createTable('{{%commerce_sitesettings}}', [
            'siteId' => $this->integer(),
            'storeId' => $this->integer()->null(), // defaults to primary store in app
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m230110_052712_sites_settings cannot be reverted.\n";
        return false;
    }
}
