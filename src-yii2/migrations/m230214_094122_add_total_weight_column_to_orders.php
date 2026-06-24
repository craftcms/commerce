<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;

/**
 * m230214_094122_add_total_weight_column_to_orders migration.
 */
class m230214_094122_add_total_weight_column_to_orders extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addColumn(Table::ORDERS, 'totalWeight', $this->decimal(14, 4)->defaultValue(0)->unsigned());

        if ($this->db->getIsMysql()) {
            $this->execute('
                UPDATE ' . Table::ORDERS . ' o
                LEFT JOIN (
                    SELECT [[orderId]], SUM([[weight]]) AS [[totalWeight]]
                    FROM ' . Table::LINEITEMS . '
                    GROUP BY [[orderId]]
                ) agg ON agg.[[orderId]] = o.[[id]]
                SET o.[[totalWeight]] = COALESCE(agg.[[totalWeight]], 0)
            ');
        } else {
            $this->execute('
                UPDATE ' . Table::ORDERS . ' o
                SET [[totalWeight]] = COALESCE(agg.[[totalWeight]], 0)
                FROM (
                    SELECT [[orderId]], SUM([[weight]]) AS [[totalWeight]]
                    FROM ' . Table::LINEITEMS . '
                    GROUP BY [[orderId]]
                ) agg
                WHERE o.[[id]] = agg.[[orderId]]
            ');
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m230214_094122_add_total_weight_column_to_orders cannot be reverted.\n";
        return false;
    }
}
