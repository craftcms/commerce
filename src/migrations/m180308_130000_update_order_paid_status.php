<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use craft\db\Migration;
use craft\db\Query;

/**
 * m180308_130000_update_order_paid_status migration.
 */
class m180308_130000_update_order_paid_status extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $orders = (new Query())->select('*')->from('{{%commerce_orders}}')->limit(null)->all();

        foreach ($orders as $order) {
            $totalPrice = $order['totalPrice'];
            $totalPaid = $order['totalPaid'];
            $isCompleted = $order['isCompleted'];

            if ($isCompleted) {
                $status = 'unpaid';

                if ($totalPaid != 0 && $totalPaid >= $totalPrice) {
                    $status = 'paid';
                }
                if ($totalPaid != 0 && $totalPaid < $totalPrice) {
                    $status = 'partial';
                }
                $data = [
                    'paidStatus' => $status
                ];
                $this->update('{{%commerce_orders}}', $data, ['id' => $order['id']]);
            }
        }
        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m180308_130000_update_order_paid_status cannot be reverted.\n";
        return false;
    }
}
