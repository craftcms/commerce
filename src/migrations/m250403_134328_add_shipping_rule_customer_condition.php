<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;

/**
 * m250403_134328_add_shipping_rule_customer_condition migration.
 */
class m250403_134328_add_shipping_rule_customer_condition extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addColumn(Table::SHIPPINGRULES, 'customerCondition', $this->text());

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m250403_134328_add_shipping_rule_customer_condition cannot be reverted.\n";
        return false;
    }
}
