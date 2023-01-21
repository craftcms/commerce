<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;

/**
 * m230121_082354_rename_purchasable_stores_price_columns migration.
 */
class m230121_082354_rename_purchasable_stores_price_columns extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->renameColumn(Table::PURCHASABLES_STORES, 'price', 'basePrice');
        $this->renameColumn(Table::PURCHASABLES_STORES, 'promotionalPrice', 'basePromotionalPrice');

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m230121_082354_rename_purchasable_stores_price_columns cannot be reverted.\n";
        return false;
    }
}
