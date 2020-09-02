<?php

namespace craft\commerce\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Query as CraftQuery;
use yii\db\Expression;

/**
 * m200902_071515_add_itemSubtotal_order_column migration.
 */
class m200902_071515_add_itemSubtotal_order_column extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        if (!$this->db->columnExists('{{%commerce_orders}}', 'itemSubtotal')) {
            $this->addColumn('{{%commerce_orders}}', 'itemSubtotal', $this->decimal(14, 4)->defaultValue(0));
        }

        // Get sums
        $lineItemSubtotals = (new CraftQuery())
            ->from('{{%commerce_lineitems}}')
            ->select([
                new Expression('SUM([[subtotal]]) as subsum'),
                'orderId',
            ])
            ->groupBy('orderId')
            ->indexBy('orderId');

        foreach ($lineItemSubtotals->batch(500) as $batch) {
            // Build cases statement
            $cases = '';
            foreach ($batch as $row) {
                $cases .= '
                WHEN [[id]] = ' . $row['orderId'] . ' THEN ' . $row['subsum'];
            }
            $cases .= '
            ';

            // Update orders
            $this->update('{{%commerce_orders}}', [
                'itemSubtotal' => new Expression('CASE ' . $cases . ' END')
            ], ['id' => array_keys($batch)]);
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m200902_071515_add_itemSubtotal_order_column cannot be reverted.\n";
        return false;
    }
}
