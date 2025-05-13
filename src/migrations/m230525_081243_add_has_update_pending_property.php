<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;

/**
 * m230525_081243_add_has_update_pending_property migration.
 */
class m230525_081243_add_has_update_pending_property extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addColumn(Table::CATALOG_PRICING, 'hasUpdatePending', $this->boolean()->notNull()->defaultValue(false));

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m230525_081243_add_has_update_pending_property cannot be reverted.\n";
        return false;
    }
}
