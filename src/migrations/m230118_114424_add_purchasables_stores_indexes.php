<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;

/**
 * m230118_114424_add_purchasables_stores_indexes migration.
 */
class m230118_114424_add_purchasables_stores_indexes extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->createIndexIfMissing(Table::PURCHASABLES_STORES, ['purchasableId']);
        $this->createIndexIfMissing(Table::PURCHASABLES_STORES, ['storeId']);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m230118_114424_add_purchasables_stores_indexes cannot be reverted.\n";
        return false;
    }
}
