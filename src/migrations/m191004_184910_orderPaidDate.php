<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use Craft;
use craft\db\Migration;

/**
 * m191004_184910_orderPaidDate migration.
 */
class m191004_184910_orderPaidDate extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        // This query looks for any order where there is an amount owing and sets the date paid to null.
        // This goes along with this fix: https://github.com/craftcms/commerce/commit/d7c9e32dfe9a5158a044e560470b627411739041

        // NOTE: You CANNOT use a table alias in the SET statement of a postgres query
        // in the example below it needs to be [[datePaid]] and NOT [[o.datePaid]]
        $sql = "
            UPDATE {{%commerce_orders}} o
            SET [[datePaid]] = null
            WHERE (SELECT SUM(CASE WHEN [[t.type]] = 'refund' THEN amount
                                   WHEN [[t.type]] IN ('purchase', 'capture') THEN -amount
                              END)
            FROM {{%commerce_transactions}} AS t
            WHERE [[t.orderId]] = [[o.id]] AND [[t.status]] = 'success' AND [[o.totalPrice]] != 0 
            ) > 0;
        ";

        Craft::$app->getDb()->createCommand($sql)->execute();
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m191004_184910_orderPaidDate cannot be reverted.\n";
        return false;
    }
}
