<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;

/**
 * m230126_105337_rename_discount_sales_references migration.
 */
class m230126_105337_rename_discount_sales_references extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->renameColumn(Table::DISCOUNTS, 'excludeOnSale', 'excludeOnPromotion');
        $this->renameColumn(Table::DISCOUNTS, 'ignoreSales', 'ignorePromotions');

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m230126_105337_rename_discount_sales_references cannot be reverted.\n";
        return false;
    }
}
