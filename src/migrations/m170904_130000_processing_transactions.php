<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m170904_130000_processing_transactions
 */
class m170904_130000_processing_transactions extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->dropColumn('{{%commerce_transactions}}', 'gatewayProcessing');

        $this->alterColumn('{{%commerce_transactions}}', 'status', $this->enum('status', ['pending', 'redirect', 'success', 'failed', 'processing'])->notNull());

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m170904_130000_processing_transactions cannot be reverted.\n";

        return false;
    }
}
