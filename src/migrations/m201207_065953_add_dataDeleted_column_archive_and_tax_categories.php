<?php

namespace craft\commerce\migrations;

use Craft;
use craft\db\Migration;

/**
 * m201207_065953_add_dataDeleted_column_archive_and_tax_categories migration.
 */
class m201207_065953_add_dataDeleted_column_archive_and_tax_categories extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        if (!$this->db->columnExists('{{%commerce_shippingcategories}}', 'dateDeleted')) {
            $this->addColumn('{{%commerce_shippingcategories}}', 'dateDeleted', $this->dateTime()->after('dateUpdated'));
        }        
        
        if (!$this->db->columnExists('{{%commerce_taxcategories}}', 'dateDeleted')) {
            $this->addColumn('{{%commerce_taxcategories}}', 'dateDeleted', $this->dateTime()->after('dateUpdated'));
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m201207_065953_add_dataDeleted_column_archive_and_tax_categories cannot be reverted.\n";
        return false;
    }
}
