<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;

/**
 * m230928_095544_fix_unique_on_some_tables migration.
 */
class m230928_095544_fix_unique_on_some_tables extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->dropIndexIfExists(Table::SHIPPINGMETHODS, 'name', true);
        $this->dropIndexIfExists(Table::TAXZONES, 'name', true);

        $this->createIndex(null, Table::SHIPPINGMETHODS, 'name', false);
        $this->createIndex(null, Table::TAXZONES, 'name', false);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m230928_095544_fix_unique_on_some_tables cannot be reverted.\n";
        return false;
    }
}
