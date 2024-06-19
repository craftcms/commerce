<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;

/**
 * m240619_082224_add_product_and_variant_conditions_to_catalog_pricing_rules migration.
 */
class m240619_082224_add_product_and_variant_conditions_to_catalog_pricing_rules extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addColumn(Table::CATALOG_PRICING_RULES, 'productCondition', $this->text()->after('applyPriceType'));
        $this->addColumn(Table::CATALOG_PRICING_RULES, 'variantCondition', $this->text()->after('applyPriceType'));

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m240619_082224_add_product_and_variant_conditions_to_catalog_pricing_rules cannot be reverted.\n";
        return false;
    }
}
