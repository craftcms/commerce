<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use craft\commerce\elements\Order;
use craft\db\Migration;
use yii\db\Expression;

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
        $this->update('{{%commerce_orders}}', [
            'paidStatus' => Order::PAID_STATUS_PAID,
        ], [
            'and',
            ['isCompleted' => true],
            ['>', 'totalPaid', 0],
            new Expression('[[totalPaid]] >= [[totalPrice]]'),
        ], [], false);

        $this->update('{{%commerce_orders}}', [
            'paidStatus' => Order::PAID_STATUS_PARTIAL,
        ], [
            'and',
            ['isCompleted' => true],
            ['>', 'totalPaid', 0],
            new Expression('[[totalPaid]] < [[totalPrice]]'),
        ], [], false);

        $this->update('{{%commerce_orders}}', [
            'paidStatus' => Order::PAID_STATUS_UNPAID,
        ], [
            'isCompleted' => true,
            'paidStatus' => null,
        ], [], false);
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
