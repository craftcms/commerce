<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m170828_130000_transaction_gatewayProcessing_flag
 */
class m170828_130000_transaction_gatewayProcessing_flag extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addColumn('{{%commerce_transactions}}', 'gatewayProcessing', $this->boolean());

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m170828_130000_transaction_gatewayProcessing_flag cannot be reverted.\n";

        return false;
    }
}
