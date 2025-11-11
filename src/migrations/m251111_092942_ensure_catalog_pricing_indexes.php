<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;

/**
 * m251111_092942_ensure_catalog_pricing_indexes migration.
 */
class m251111_092942_ensure_catalog_pricing_indexes extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->createIndexIfMissing(Table::CATALOG_PRICING, 'catalogPricingRuleId', false);
        $this->createIndexIfMissing(Table::CATALOG_PRICING, 'isPromotionalPrice', false);
        $this->createIndexIfMissing(Table::CATALOG_PRICING, 'purchasableId', false);
        $this->createIndexIfMissing(Table::CATALOG_PRICING, 'storeId', false);
        $this->createIndexIfMissing(Table::CATALOG_PRICING, 'userId', false);
        $this->createIndexIfMissing(Table::CATALOG_PRICING, ['purchasableId', 'storeId', 'isPromotionalPrice', 'price', 'catalogPricingRuleId', 'dateFrom', 'dateTo'], false);
        $this->createIndexIfMissing(Table::CATALOG_PRICING, ['purchasableId', 'storeId', 'isPromotionalPrice', 'price'], false);
        $this->createIndexIfMissing(Table::CATALOG_PRICING, ['purchasableId', 'storeId'], false);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m251111_092942_ensure_catalog_pricing_indexes cannot be reverted.\n";
        return false;
    }
}
