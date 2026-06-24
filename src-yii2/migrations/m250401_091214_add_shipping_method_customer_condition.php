<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;

/**
 * m250401_091214_add_shipping_method_customer_condition migration.
 */
class m250401_091214_add_shipping_method_customer_condition extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addColumn(Table::SHIPPINGMETHODS, 'customerCondition', $this->text());

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m250401_091214_add_shipping_method_customer_condition cannot be reverted.\n";
        return false;
    }
}
