<?php

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m200207_161707_sku_description_on_lineitem migration.
 */
class m200207_161707_sku_description_on_lineitem extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        if (!$this->db->columnExists('{{%commerce_lineitems}}', 'sku')) {
            $this->addColumn('{{%commerce_lineitems}}', 'sku', $this->string());
        }
        if (!$this->db->columnExists('{{%commerce_lineitems}}', 'description')) {
            $this->addColumn('{{%commerce_lineitems}}', 'description', $this->string());
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m200207_161707_sku_description_on_lineitem cannot be reverted.\n";
        return false;
    }
}
