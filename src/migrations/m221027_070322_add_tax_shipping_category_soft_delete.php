<?php

namespace craft\commerce\migrations;

use Craft;
use craft\db\Migration;

/**
 * m221027_070322_add_tax_shipping_category_soft_delete migration.
 */
class m221027_070322_add_tax_shipping_category_soft_delete extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if (!$this->db->columnExists('{{%commerce_taxcategories}}', 'dateDeleted')) {
            $this->addColumn('{{%commerce_taxcategories}}', 'dateDeleted', $this->dateTime()->after('default'));
        }

        if (!$this->db->columnExists('{{%commerce_shippingcategories}}', 'dateDeleted')) {
            $this->addColumn('{{%commerce_shippingcategories}}', 'dateDeleted', $this->dateTime()->after('default'));
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m221027_070322_add_tax_shipping_category_soft_delete cannot be reverted.\n";
        return false;
    }
}
