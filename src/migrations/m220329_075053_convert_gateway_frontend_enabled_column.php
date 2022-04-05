<?php

namespace craft\commerce\migrations;

use Craft;
use craft\db\Migration;

/**
 * m220329_075053_convert_gateway_frontend_enabled_column migration.
 */
class m220329_075053_convert_gateway_frontend_enabled_column extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->alterColumn('{{%commerce_gateways}}', 'isFrontendEnabled', $this->string(500)->notNull()->defaultValue('1'));
        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m220329_075053_convert_gateway_frontend_enabled_column cannot be reverted.\n";
        return false;
    }
}
