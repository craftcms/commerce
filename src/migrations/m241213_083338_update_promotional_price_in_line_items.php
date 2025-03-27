<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;
use craft\db\Query;
use yii\db\Expression;

/**
 * m241213_083338_update_promotional_price_in_line_items migration.
 */
class m241213_083338_update_promotional_price_in_line_items extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $ordersQuery = (new Query())
            ->select('id')
            ->from(Table::ORDERS)
            ->where(['isCompleted' => true]);

        $lineItemsQuery = (new Query())
            ->select('id')
            ->from(Table::LINEITEMS)
            ->where(['orderId' => $ordersQuery])
            ->andWhere(['promotionalPrice' => null])
            ->andWhere(new Expression('[[salePrice]] < [[price]]'))
            ->column();

        foreach (array_chunk($lineItemsQuery, 1000) as $chunk) {
            $this->update(Table::LINEITEMS, ['promotionalPrice' => new Expression('[[salePrice]]')], ['id' => $chunk]);
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m241213_083338_update_promotional_price_in_line_items cannot be reverted.\n";
        return false;
    }
}
