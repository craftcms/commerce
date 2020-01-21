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
        $this->delete('{{%commerce_customers}} c', [
            'id' => (new Query())
                ->select(['c2.id'])
                ->from('{{%commerce_customers}} c2')
                ->leftJoin('{{%commerce_orders}} o', '[[c2.id]] = [[o.customerId]]')
                ->where(['o.id' => null, 'c2.userId' => null])
        ]);
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
