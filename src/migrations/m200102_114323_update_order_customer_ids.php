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
            ->select('[[orders.email]] email, [[orders.customerId]] customerId')
            ->from('{{%commerce_orders}} orders')
            ->innerJoin('{{%commerce_customers}} customers', '[[customers.id]] = [[orders.customerId]]')
            ->orderBy('[[orders.dateOrdered]] DESC')
            ->where(['[[orders.isCompleted]]' => true])
            ->all();

        $emails = [];
        foreach ($allCustomers as $customer) {
            $email = $customer['email'];
            $customerId = $customer['customerId'];
            // uses the last order email as the customerId
            $emails[$email] = $customerId;
        }

        foreach ($emails as $email => $customerId) {
            $ids = (new Query())
                ->select('[[orders.id]] id')
                ->from('{{%commerce_orders}} orders')
                ->where([
                    'and',
                    ['not', ['[[orders.customerId]]' => $customerId]],
                    ['[[email]]' => $email],
                    ['[[orders.isCompleted]]' => true],
                ])
                ->column();

            if ($ids) {
                $this->update('{{%commerce_orders}} orders', ['customerId' => $customer['customerId']], ['id' => $ids]);
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
