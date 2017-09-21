<?php

namespace craft\commerce\migrations;

use craft\db\Migration;
use craft\db\Query;
use craft\helpers\MigrationHelper;
use craft\helpers\StringHelper;

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
