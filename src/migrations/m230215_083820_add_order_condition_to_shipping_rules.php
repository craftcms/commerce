<?php

namespace craft\commerce\migrations;

use Craft;
use craft\commerce\db\Table;
use craft\db\Migration;

/**
 * m230215_083820_add_order_condition_to_shipping_rules migration.
 */
class m230215_083820_add_order_condition_to_shipping_rules extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addColumn(Table::SHIPPINGRULES, 'orderCondition', $this->text());

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m230215_083820_add_order_condition_to_shipping_rules cannot be reverted.\n";
        return false;
    }
}
