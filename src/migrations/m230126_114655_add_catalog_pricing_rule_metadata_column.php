<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;

/**
 * m230126_114655_add_catalog_pricing_rule_metadata_column migration.
 */
class m230126_114655_add_catalog_pricing_rule_metadata_column extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addColumn(Table::CATALOG_PRICING_RULES, 'metadata', $this->text());

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m230126_114655_add_catalog_pricing_rule_metadata_column cannot be reverted.\n";
        return false;
    }
}
