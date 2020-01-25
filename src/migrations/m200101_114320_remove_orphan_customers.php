<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;

/**
 * m200102_114323_update_order_customer_ids migration.
 */
class m200101_114320_remove_orphan_customers extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        // Delete all customer records (and their addresses) which aren't related to any orders
        // and don't have a user ID.
        $subSubQuery = (new Query())
            ->select(['[[cc.id]]'])
            ->from('{{%commerce_customers}} cc')
            ->leftJoin('{{%commerce_orders}} o', '[[cc.id]] = [[o.customerId]]')
            ->where(['o.id' => null, 'cc.userId' => null]);

        $subQuery = (new Query())
            ->select(['sqid.id'])
            ->from(['sqid' => $subSubQuery]);

        // https://stackoverflow.com/a/14302701/167827
        $this->delete('{{%commerce_customers}}', ['in', 'id', $subQuery]);
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m200102_114323_update_order_customer_ids cannot be reverted.\n";
        return false;
    }
}
