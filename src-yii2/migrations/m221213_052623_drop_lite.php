<?php

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m221213_052623_drop_lite migration.
 */
class m221213_052623_drop_lite extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if ($this->db->columnExists('{{%commerce_shippingmethods}}', 'isLite')) {
            $this->dropColumn('{{%commerce_shippingmethods}}', 'isLite');
        }
        if ($this->db->columnExists('{{%commerce_taxrates}}', 'isLite')) {
            $this->dropColumn('{{%commerce_taxrates}}', 'isLite');
        }
        if ($this->db->columnExists('{{%commerce_shippingrules}}', 'isLite')) {
            $this->dropColumn('{{%commerce_shippingrules}}', 'isLite');
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m221213_052623_drop_lite cannot be reverted.\n";
        return false;
    }
}
