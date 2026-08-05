<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;
use craft\db\Table as CraftTable;

/**
 * m260507_000000_subscriptions_nullable_userId migration.
 */
class m260507_000000_subscriptions_nullable_userId extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Drop the existing RESTRICT FK and replace with CASCADE so subscriptions
        // are hard-deleted when their user is hard-deleted.
        $this->dropForeignKeyIfExists(Table::SUBSCRIPTIONS, ['userId']);
        $this->addForeignKey(null, Table::SUBSCRIPTIONS, ['userId'], CraftTable::USERS, ['id'], 'CASCADE');

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m260507_000000_subscriptions_nullable_userId cannot be reverted.\n";
        return false;
    }
}
