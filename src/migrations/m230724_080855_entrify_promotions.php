<?php

namespace craft\commerce\migrations;

use Craft;
use craft\commerce\db\Table;
use craft\db\Migration;
use craft\db\Table as CraftTable;
use craft\helpers\Db;
use craft\helpers\MigrationHelper;

/**
 * m230724_080855_entrify_promotions migration.
 */
class m230724_080855_entrify_promotions extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Drop all FKs
        Db::dropForeignKeyIfExists(Table::DISCOUNT_CATEGORIES, ['categoryId'], $this->db);
        Db::dropForeignKeyIfExists(Table::DISCOUNT_CATEGORIES, ['discountId'], $this->db);
        Db::dropForeignKeyIfExists(Table::SALE_CATEGORIES, ['categoryId'], $this->db);
        Db::dropForeignKeyIfExists(Table::SALE_CATEGORIES, ['saleId'], $this->db);

        // Add the FKs back but to the Elements table not the categories table
        $this->addForeignKey(null, Table::DISCOUNT_CATEGORIES, ['categoryId'], CraftTable::ELEMENTS, ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::DISCOUNT_CATEGORIES, ['discountId'], Table::DISCOUNTS, ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::SALE_CATEGORIES, ['categoryId'], CraftTable::ELEMENTS, ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::SALE_CATEGORIES, ['saleId'], Table::SALES, ['id'], 'CASCADE', 'CASCADE');

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m230724_080855_entrify_promotions cannot be reverted.\n";
        return false;
    }
}
