<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;
use craft\db\Query;
use yii\db\Expression;

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

        $sums = (new Query())
            ->select([new Expression('SUM(weight) as [[totalWeight]]'), '[[orderId]]'])
            ->from(Table::LINEITEMS)
            ->indexBy('orderId')
            ->groupBy('[[orderId]]')
            ->all();

        $idsByWeight = [];
        foreach ($sums as $sum) {
            if (!isset($idsByWeight[$sum['totalWeight']])) {
                $idsByWeight[$sum['totalWeight']] = [];
            }

            $idsByWeight[$sum['totalWeight']][] = $sum['orderId'];
        }

        $cases = [];
        foreach ($idsByWeight as $totalWeight => $ids) {
            $cases[] = 'WHEN id IN (' . implode(', ', $ids) . ') THEN ' . $totalWeight;
        }

        if (!empty($cases)) {
            $batches = array_chunk($cases, 5);
            foreach ($batches as $batch) {
                $this->update(
                    Table::ORDERS,
                    ['totalWeight' => new Expression(sprintf('(CASE %s END)', implode(' ', $batch)))],
                    [],
                    [],
                    false,
                );
            }
        }

        $this->update(Table::ORDERS, ['totalWeight' => 0], ['totalWeight' => null], [], false);


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
