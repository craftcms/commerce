<?php

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m220912_111800_add_order_total_qty_column migration.
 */
class m220912_111800_add_order_total_qty_column extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if (!$this->db->columnExists('{{%commerce_orders}}', 'totalQty')) {
            $this->addColumn('{{%commerce_orders}}', 'totalQty', $this->integer()->unsigned());

            if ($this->db->getIsMysql()) {
                $this->execute('
                    UPDATE {{%commerce_orders}} o
                    LEFT JOIN (
                        SELECT [[orderId]], SUM([[qty]]) AS [[totalQty]]
                        FROM {{%commerce_lineitems}}
                        GROUP BY [[orderId]]
                    ) agg ON agg.[[orderId]] = o.[[id]]
                    SET o.[[totalQty]] = COALESCE(agg.[[totalQty]], 0)
                ');
            } else {
                $this->execute('
                    UPDATE {{%commerce_orders}} o
                    SET [[totalQty]] = COALESCE(agg.[[totalQty]], 0)
                    FROM (
                        SELECT [[orderId]], SUM([[qty]]) AS [[totalQty]]
                        FROM {{%commerce_lineitems}}
                        GROUP BY [[orderId]]
                    ) agg
                    WHERE o.[[id]] = agg.[[orderId]]
                ');
            }
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m220912_111800_add_order_total_qty_column cannot be reverted.\n";
        return false;
    }
}
