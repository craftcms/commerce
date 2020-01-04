<?php

namespace craft\commerce\migrations;

use Craft;
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
        $allCustomers = (new Query())
            ->select('[[orders.email]], [[orders.customerId]]')
            ->from('{{%commerce_orders}} orders')
            ->innerJoin('{{%commerce_customers}} customers', '[[customers.id]] = [[orders.customerId]]')
            ->where(['[[orders.isCompleted]]' => true])
            ->orderBy('[[customers.userId]] DESC, [[orders.dateOrdered]] ASC');

        foreach ($allCustomers->batch() as $customers) {
            foreach ($customers as $customer) {
                $this->update(
                    '{{%commerce_orders}} orders',
                    ['customerId' => $customer['customerId']],
                    [
                        'and',
                        ['not', ['[[orders.customerId]]' => $customer['customerId']]],
                        ['[[email]]' => $customer['email']],
                        ['[[orders.isCompleted]]' => true],
                    ]
                );
            }
        }
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
