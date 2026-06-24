<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;

/**
 * m240430_161804_add_index_to_transaction_hash migration.
 */
class m240430_161804_add_index_to_transaction_hash extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->createIndexIfMissing(Table::TRANSACTIONS, 'hash', false);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m240430_161804_add_index_to_transaction_hash cannot be reverted.\n";
        return false;
    }
}
