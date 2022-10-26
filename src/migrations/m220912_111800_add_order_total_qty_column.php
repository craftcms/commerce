<?php

namespace craft\commerce\migrations;

use craft\db\Migration;
use craft\db\Query;
use yii\db\Expression;

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

            $sums = (new Query())
                ->select([new Expression('SUM(qty) as [[totalQty]]'), '[[orderId]]'])
                ->from('{{%commerce_lineitems}}')
                ->indexBy('orderId')
                ->groupBy('[[orderId]]')
                ->all();

            $idsByQty = [];
            foreach ($sums as $sum) {
                if (!isset($idsByQty[$sum['totalQty']])) {
                    $idsByQty[$sum['totalQty']] = [];
                }

                $idsByQty[$sum['totalQty']][] = $sum['orderId'];
            }

            $cases = [];
            foreach ($idsByQty as $totalQty => $ids) {
                $cases[] = 'WHEN id IN (' . implode(', ', $ids) . ') THEN ' . $totalQty;
            }

            if (!empty($cases)) {
                $batches = array_chunk($cases, 5);
                foreach ($batches as $batch) {
                    $this->update(
                        '{{%commerce_orders}}',
                        ['totalQty' => new Expression(sprintf('(CASE %s END)', implode(' ', $batch)))],
                        [],
                        [],
                        false,
                    );
                }
            }

            $this->update('{{%commerce_orders}}', ['totalQty' => 0], ['totalQty' => null], [], false);
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
