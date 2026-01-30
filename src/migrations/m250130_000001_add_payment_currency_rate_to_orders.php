<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;

/**
 * m250130_000001_add_payment_currency_rate_to_orders migration.
 */
class m250130_000001_add_payment_currency_rate_to_orders extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if (!$this->db->columnExists(Table::ORDERS, 'paymentCurrencyRates')) {
            $this->addColumn(Table::ORDERS, 'paymentCurrencyRates', $this->text()->null());
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m250130_000001_add_payment_currency_rate_to_orders cannot be reverted.\n";
        return false;
    }
}
