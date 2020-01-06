<?php

namespace craft\commerce\migrations;

use Craft;
use craft\commerce\queue\ConsolidateGuestOrders;
use craft\commerce\records\Order;
use craft\db\Migration;
use craft\db\Query;
use craft\helpers\ArrayHelper;

/**
 * m200102_114323_update_order_customer_ids migration.
 */
class m200102_114323_update_order_customer_ids extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {

        // get a list of emails and customerIds from all completed orders
        $allCustomers = (new Query())
            ->select('[[orders.email]] email')
            ->from('{{%commerce_orders}} orders')
            ->where(['[[orders.isCompleted]]' => true])
            ->column();

        $appearMoreThanOnce = array_keys(array_filter(array_count_values($allCustomers), static function($v){
            return $v > 1;
        }));

        // Consolidate guest orders
        Craft::$app->getQueue()->push(new ConsolidateGuestOrders([
            'emails' => $appearMoreThanOnce
        ]));
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
