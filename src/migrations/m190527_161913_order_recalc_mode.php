<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m190527_161913_order_recalc_mode migration.
 */
class m190527_161913_order_recalc_mode extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn('{{%commerce_orders}}', 'recalculationMode', $this->enum('recalculationMode', ['all', 'none', 'adjustmentsOnly'])->notNull()->defaultValue('all'));
        // All carts have recalculation mode of 'all' to start
        $this->update('{{%commerce_orders}}', ['recalculationMode' => 'all'], ['isCompleted' => false]);
        // All orders have recalculation mode of 'none' to start
        $this->update('{{%commerce_orders}}', ['recalculationMode' => 'none'], ['isCompleted' => true]);
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m190527_161913_order_recalc_mode cannot be reverted.\n";
        return false;
    }
}
