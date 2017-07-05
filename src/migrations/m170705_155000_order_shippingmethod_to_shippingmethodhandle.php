<?php

namespace craft\commerce\migrations;

use craft\commerce\records\Order;
use craft\db\Migration;

/**
 * m170705_155000_order_shippingmethod_to_shippingmethodhandle
 */
class m170705_155000_order_shippingmethod_to_shippingmethodhandle extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->renameColumn(Order::tableName(), 'shippingMethod', 'shippingMethodHandle');

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m170705_155000_order_shippingmethod_to_shippingmethodhandle cannot be reverted.\n";


        return false;
    }
}
