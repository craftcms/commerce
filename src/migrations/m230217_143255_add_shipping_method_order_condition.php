<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;

/**
 * m230217_143255_add_shipping_method_order_condition migration.
 */
class m230217_143255_add_shipping_method_order_condition extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addColumn(Table::SHIPPINGMETHODS, 'orderCondition', $this->text());

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m230217_143255_add_shipping_method_order_condition cannot be reverted.\n";
        return false;
    }
}
