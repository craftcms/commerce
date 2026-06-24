<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;

/**
 * m251105_194014_add_icon_and_color_to_categories_and_methods migration.
 */
class m251105_194014_add_icon_and_color_to_categories_and_methods extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Add icon and color to shipping methods
        if (!$this->db->columnExists(Table::SHIPPINGMETHODS, 'icon')) {
            $this->addColumn(Table::SHIPPINGMETHODS, 'icon', $this->string()->after('handle'));
        }
        if (!$this->db->columnExists(Table::SHIPPINGMETHODS, 'color')) {
            $this->addColumn(Table::SHIPPINGMETHODS, 'color', $this->string()->after('icon'));
        }

        // Add icon and color to shipping categories
        if (!$this->db->columnExists(Table::SHIPPINGCATEGORIES, 'icon')) {
            $this->addColumn(Table::SHIPPINGCATEGORIES, 'icon', $this->string()->after('handle'));
        }
        if (!$this->db->columnExists(Table::SHIPPINGCATEGORIES, 'color')) {
            $this->addColumn(Table::SHIPPINGCATEGORIES, 'color', $this->string()->after('icon'));
        }

        // Add icon and color to tax categories
        if (!$this->db->columnExists(Table::TAXCATEGORIES, 'icon')) {
            $this->addColumn(Table::TAXCATEGORIES, 'icon', $this->string()->after('handle'));
        }
        if (!$this->db->columnExists(Table::TAXCATEGORIES, 'color')) {
            $this->addColumn(Table::TAXCATEGORIES, 'color', $this->string()->after('icon'));
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m251105_194014_add_icon_and_color_to_categories_and_methods cannot be reverted.\n";
        return false;
    }
}
