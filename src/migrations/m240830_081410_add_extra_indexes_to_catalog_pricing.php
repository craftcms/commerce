<?php

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m240830_081410_add_extra_indexes_to_catalog_pricing migration.
 */
class m240830_081410_add_extra_indexes_to_catalog_pricing extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->createIndex(null, '{{%commerce_catalogpricing}}', ['purchasableId', 'storeId', 'isPromotionalPrice', 'price'], false);
        $this->createIndex(null, '{{%commerce_catalogpricing}}', ['purchasableId', 'storeId', 'isPromotionalPrice', 'price', 'catalogPricingRuleId', 'dateFrom', 'dateTo'], false);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m240830_081410_add_extra_indexes_to_catalog_pricing cannot be reverted.\n";
        return false;
    }
}
