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
        $customers = (new Query())
            ->select(['[[customers.id]] id'])
            ->from('{{%commerce_customers}} customers')
            ->leftJoin('{{%commerce_orders}} orders', '[[customers.id]] = [[orders.customerId]]')
            ->where(['[[orders.customerId]]' => null, '[[customers.userId]]' => null])
            ->column();

        // This will also remove all addresses related to the customer.
        Craft::$app->getDb()->createCommand()
            ->delete('{{%commerce_customers}}', ['id' => $customers])
            ->execute();
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
