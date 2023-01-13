<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;

/**
 * m230113_195425_add_remove_variants_sku_column migration.
 */
class m230113_195425_add_remove_variants_sku_column extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->dropColumn(Table::VARIANTS, 'sku');

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m230113_195425_add_remove_variants_sku_column cannot be reverted.\n";
        return false;
    }
}
