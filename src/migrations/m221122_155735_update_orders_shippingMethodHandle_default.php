<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;

/**
 * m221122_155735_update_orders_shippingMethodHandle_default migration.
 */
class m221122_155735_update_orders_shippingMethodHandle_default extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->update(
            Table::ORDERS,
            ['shippingMethodHandle' => ''],
            ['shippingMethodHandle' => null],
            updateTimestamp: false,
        );
        $this->alterColumn('{{%commerce_orders}}', 'shippingMethodHandle', $this->string()->notNull()->defaultValue(''));

        $this->update(
            Table::ORDERS,
            ['shippingMethodName' => ''],
            ['shippingMethodName' => null],
            updateTimestamp: false,
        );
        $this->alterColumn('{{%commerce_orders}}', 'shippingMethodName', $this->string()->notNull()->defaultValue(''));

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m221122_155735_update_orders_shippingMethodHandle_default cannot be reverted.\n";
        return false;
    }
}
