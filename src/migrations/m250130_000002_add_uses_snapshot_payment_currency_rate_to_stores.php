<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;

/**
 * m250130_000002_add_uses_snapshot_payment_currency_rate_to_stores migration.
 */
class m250130_000002_add_uses_snapshot_payment_currency_rate_to_stores extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if (!$this->db->columnExists(Table::STORES, 'usesSnapshotPaymentCurrencyRate')) {
            $this->addColumn(Table::STORES, 'usesSnapshotPaymentCurrencyRate', $this->string()->notNull()->defaultValue('false'));
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m250130_000002_add_uses_snapshot_payment_currency_rate_to_stores cannot be reverted.\n";
        return false;
    }
}
