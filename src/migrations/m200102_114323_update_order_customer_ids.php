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

        // get a list of emails and customerIds from all completed orders
        $allCustomers = (new Query())
            ->select('[[orders.email]] email, [[orders.customerId]] customerId')
            ->from('{{%commerce_orders}} orders')
            ->leftJoin('{{%commerce_customers}} customers', '[[customers.id]] = [[orders.customerId]]')
            ->where(['[[orders.isCompleted]]' => true])
            ->distinct()
            ->all();

        // for each unique combination of email and customerId set all orders for that email to the customerId
        foreach ($allCustomers as $customer) {
            $this->update('{{%commerce_orders}} orders', ['customerId' => $customer['customerId']], [
                'and',
                ['not', ['[[orders.customerId]]' => $customer['customerId']]],
                ['[[email]]' => $customer['email']],
                ['[[orders.isCompleted]]' => true],
            ]);
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
