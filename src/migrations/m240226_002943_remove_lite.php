<?php

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m240226_002943_remove_lite migration.
 */
class m240226_002943_remove_lite extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // if column exists
        if ($this->db->columnExists('{{%commerce_shippingmethods}}', 'isLite')) {
            $this->dropColumn('{{%commerce_shippingmethods}}', 'isLite');
        }
        if ($this->db->columnExists('{{%commerce_shippingrules}}', 'isLite')) {
            $this->dropColumn('{{%commerce_shippingrules}}', 'isLite');
        }
        if ($this->db->columnExists('{{%commerce_taxrates}}', 'isLite')) {
            $this->dropColumn('{{%commerce_taxrates}}', 'isLite');
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m240226_002943_remove_lite cannot be reverted.\n";
        return false;
    }
}
