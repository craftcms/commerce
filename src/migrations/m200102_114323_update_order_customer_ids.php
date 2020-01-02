<?php

namespace craft\commerce\migrations;

use Craft;
use craft\commerce\records\Order;
use craft\db\Migration;

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
        $customers = Order::find()
            ->select('orders.email, orders.customerId')
            ->from('{{%commerce_orders}} orders')
            ->innerJoin('{{%commerce_customers}} customers', 'customers.id = orders.customerId')
            ->where(['isCompleted' => 1])
            // If they have a user account make sure we associate the orders
            // to that customer
            ->orderBy('userId DESC, dateOrdered ASC')
            ->groupBy('email')
            ->indexBy('customerId')
            ->asArray()
            ->column();

        $customers = array_filter($customers);

        if (empty($customers)) {
            return;
        }

        $transaction = $this->db->beginTransaction();

        try {
            foreach ($customers as $customerId => $email) {
                $this->update(
                    '{{%commerce_orders}}',
                    ['customerId' => $customerId],
                    [
                        'and',
                        ['not', ['customerId' => $customerId]],
                        ['email' => $email],
                        ['isCompleted' => 1],
                    ]
                );
            }

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
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
