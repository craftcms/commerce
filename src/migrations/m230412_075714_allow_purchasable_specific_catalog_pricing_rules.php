<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;

/**
 * m230412_075714_allow_purchasable_specific_catalog_pricing_rules migration.
 */
class m230412_075714_allow_purchasable_specific_catalog_pricing_rules extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addColumn(Table::CATALOG_PRICING_RULES, 'purchasableId', $this->integer());
        $this->createIndex(null, Table::CATALOG_PRICING_RULES, 'purchasableId', false);
        $this->addForeignKey(null, Table::CATALOG_PRICING_RULES, ['purchasableId'], Table::PURCHASABLES, ['id'], 'CASCADE', 'CASCADE');

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m230412_075714_allow_purchasable_specific_catalog_pricing_rules cannot be reverted.\n";
        return false;
    }
}
