<?php

namespace craft\commerce\migrations;

use craft\db\Migration;
use craft\db\Query;

/**
 * m200602_172413_fix_orders_without_customerId migration.
 */
class m200602_172413_fix_orders_without_customerId extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        // Fix data for:
        // https://github.com/craftcms/commerce/issues/1456
        // https://github.com/craftcms/commerce/issues/1483
        // Get all completed orders without a customerId to fix
        $orders = (new Query())
            ->select(['id', 'customerId', 'email'])
            ->from('{{%commerce_orders}}')
            ->where(
                ['customerId' => null, 'isCompleted' => true]
            )->limit(null);

        foreach ($orders->batch() as $batch) {
            foreach ($batch as $order) {

                $fixed = false;

                if ($order['email']) {
                    $userId = (new Query())->select(['id'])->from('{{%users}}')->where(['email' => $order['email']])->scalar();
                    if ($userId) {
                        $customerId = (new Query())->select(['id'])->from('{{%commerce_customers}}')->where(['userId' => $userId])->scalar();
                        if ($customerId) {
                            $data = ['customerId' => $customerId];
                            $this->update('{{%commerce_orders}}', $data, ['id' => $order['id']]);
                            $fixed = true;
                        }
                    }
                }

                if (!$fixed) {
                    $this->insert('{{%commerce_customers}}',[]);
                    $customerId = $this->db->getLastInsertID();
                    $data = ['customerId' => $customerId];
                    $this->update('{{%commerce_orders}}', $data, ['id' => $order['id']]);
                }
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m200602_172413_fix_orders_without_customerId cannot be reverted.\n";
        return false;
    }
}
