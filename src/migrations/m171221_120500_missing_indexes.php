<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m171221_120500_missing_indexes
 */
class m171221_120500_missing_indexes extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {

        $this->createIndex($this->db->getIndexName('{{%commerce_gateways}}', 'handle', true), '{{%commerce_gateways}}', 'handle', true);
        $this->createIndex($this->db->getIndexName('{{%commerce_paymentsources}}', 'gatewayId', false), '{{%commerce_paymentsources}}', 'gatewayId', false);
        $this->createIndex($this->db->getIndexName('{{%commerce_paymentsources}}', 'userId', false), '{{%commerce_paymentsources}}', 'userId', false);
        $this->createIndex($this->db->getIndexName('{{%commerce_orders}}', 'paymentSourceId', false), '{{%commerce_orders}}', 'paymentSourceId', false);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m171221_120500_missing_indexes cannot be reverted.\n";

        return false;
    }
}
