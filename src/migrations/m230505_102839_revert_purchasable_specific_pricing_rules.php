<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;

/**
 * m230505_102839_revert_purchasable_specific_pricing_rules migration.
 */
class m230505_102839_revert_purchasable_specific_pricing_rules extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->dropIndex('purchasableId', Table::CATALOG_PRICING_RULES);
        $this->dropForeignKeyIfExists(Table::CATALOG_PRICING_RULES, ['purchasableId']);
        $this->dropColumn(Table::CATALOG_PRICING_RULES, 'purchasableId');

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m230505_102839_revert_purchasable_specific_pricing_rules cannot be reverted.\n";
        return false;
    }
}
