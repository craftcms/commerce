<?php

namespace craft\commerce\migrations;

use Craft;
use craft\commerce\elements\conditions\customers\CustomerOrdersCondition;
use craft\db\Migration;
use craft\db\Query;
use craft\helpers\Json;
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
                ->select([new Expression('SUM(qty) as totalQty'), '[[orderId]]'])
                ->from('{{%commerce_lineitems}}')
                ->indexBy('orderId')
                ->groupBy('[[orderId]]')
                ->all();

            $cases = [];
            foreach ($sums as $sum) {
                $cases[] = 'WHEN id=' . $sum['orderId'] . ' THEN ' . $sum['totalQty'] ?? '0';
            }

            if (!empty($cases)) {
                $batches = array_chunk($cases, 500);
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
