<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m171207_160000_order_can_store_payment_sources
 */
class m171207_160000_order_can_store_payment_sources extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addColumn('{{%commerce_orders}}', 'paymentSourceId', $this->integer());
        $this->addForeignKey($this->db->getForeignKeyName('{{%commerce_orders}}', 'paymentSourceId'), '{{%commerce_orders}}', 'paymentSourceId', '{{%commerce_paymentsources}}', 'id', 'SET NULL', null);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m171207_160000_order_can_store_payment_sources cannot be reverted.\n";

        return false;
    }
}
