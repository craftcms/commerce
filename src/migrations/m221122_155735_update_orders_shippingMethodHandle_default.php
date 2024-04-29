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

        $this->update(
            Table::ORDERS,
            ['shippingMethodName' => ''],
            ['shippingMethodName' => null],
            updateTimestamp: false,
        );

        if ($this->db->getIsPgsql()) {
            // Manually construct the SQL for Postgres
            // (see https://github.com/yiisoft/yii2/issues/12077)
            $this->execute(sprintf('ALTER TABLE %s ALTER COLUMN [[shippingMethodHandle]] SET NOT NULL', Table::ORDERS));
            $this->execute(sprintf("ALTER TABLE %s ALTER COLUMN [[shippingMethodHandle]] SET DEFAULT ''", Table::ORDERS));
            $this->execute(sprintf('ALTER TABLE %s ALTER COLUMN [[shippingMethodName]] SET NOT NULL', Table::ORDERS));
            $this->execute(sprintf("ALTER TABLE %s ALTER COLUMN [[shippingMethodName]] SET DEFAULT ''", Table::ORDERS));
        } else {
            $this->alterColumn(Table::ORDERS, 'shippingMethodHandle', $this->string()->notNull()->defaultValue(''));
            $this->alterColumn(Table::ORDERS, 'shippingMethodName', $this->string()->notNull()->defaultValue(''));
        }

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
