<?php

namespace craft\commerce\migrations;

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
        $this->db->createCommand()
            ->update('{{%commerce_orders}}', ['shippingMethodHandle' => ''], ['shippingMethodHandle' => null])
            ->execute();
        $this->alterColumn('{{%commerce_orders}}', 'shippingMethodHandle', $this->string()->notNull()->defaultValue(''));

        $this->db->createCommand()
            ->update('{{%commerce_orders}}', ['shippingMethodName' => ''], ['shippingMethodName' => null])
            ->execute();
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
