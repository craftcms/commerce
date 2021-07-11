<?php

namespace craft\commerce\migrations;

use Craft;
use craft\commerce\db\Table;
use craft\db\Migration;
use craft\helpers\MigrationHelper;

/**
 * m210703_060852_store_location_index migration.
 */
class m210703_060852_store_location_index extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        // Easier to drop all and recreate all
        MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_addresses}}', $this);
        MigrationHelper::dropAllIndexesOnTable('{{%commerce_addresses}}', $this);

        // Same as before
        $this->addForeignKey(null, '{{%commerce_addresses}}', ['countryId'], Table::COUNTRIES, ['id'], 'SET NULL');
        $this->addForeignKey(null, '{{%commerce_addresses}}', ['stateId'], Table::STATES, ['id'], 'SET NULL');

        // Same as before
        $this->createIndex(null, '{{%commerce_addresses}}', 'countryId', false);
        $this->createIndex(null, '{{%commerce_addresses}}', 'stateId', false);

        // This is the new index
        $this->createIndex(null, '{{%commerce_addresses}}', 'isStoreLocation', false);
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m210703_060852_store_location_index cannot be reverted.\n";
        return false;
    }
}
