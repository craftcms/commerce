<?php

namespace craft\commerce\migrations;

use craft\db\Migration;
use craft\db\Query;
use craft\helpers\MigrationHelper;
use craft\helpers\StringHelper;

/**
 * m170810_130000_sendCartInfo_per_gateway
 */
class m170810_130000_sendCartInfo_per_gateway extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addColumn('{{%commerce_gateways}}', 'sendCartInfo', $this->boolean());

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m170810_130000_sendCartInfo_per_gateway cannot be reverted.\n";

        return false;
    }
}
