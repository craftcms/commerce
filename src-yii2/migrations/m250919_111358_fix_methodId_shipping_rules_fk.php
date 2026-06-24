<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;

/**
 * m250919_111358_fix_methodId_shipping_rules_fk migration.
 */
class m250919_111358_fix_methodId_shipping_rules_fk extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->dropForeignKeyIfExists(Table::SHIPPINGRULES, ['methodId']);

        $this->addForeignKey(null, Table::SHIPPINGRULES, ['methodId'], Table::SHIPPINGMETHODS, ['id'], 'CASCADE');

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m250919_111358_fix_methodId_shipping_rules_fk cannot be reverted.\n";
        return false;
    }
}
