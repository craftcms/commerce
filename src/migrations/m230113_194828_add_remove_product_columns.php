<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;

/**
 * m230113_194828_add_remove_product_columns migration.
 */
class m230113_194828_add_remove_product_columns extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->dropForeignKeyIfExists(Table::PRODUCTS, ['taxCategoryId']);
        $this->dropForeignKeyIfExists(Table::PRODUCTS, ['shippingCategoryId']);

        $this->dropColumn(Table::PRODUCTS, 'taxCategoryId');
        $this->dropColumn(Table::PRODUCTS, 'shippingCategoryId');
        $this->dropColumn(Table::PRODUCTS, 'promotable');
        $this->dropColumn(Table::PRODUCTS, 'availableForPurchase');
        $this->dropColumn(Table::PRODUCTS, 'freeShipping');

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m230113_194828_add_remove_product_columns cannot be reverted.\n";
        return false;
    }
}
