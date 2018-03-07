<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m180307_130000_order_paid_status migration.
 */
class m180307_130000_order_paid_status extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn('{{%commerce_orders}}', 'paidStatus', $this->enum('paidStatus', ['paid', 'partial', 'unpaid']));

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m180307_130000_order_paid_status cannot be reverted.\n";
        return false;
    }
}
