<?php

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m241204_045158_enable_tax_rate migration.
 */
class m241204_045158_enable_tax_rate extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // if column doesnt exist
        if (!$this->db->columnExists('{{%commerce_taxrates}}', 'enabled')) {
            $this->addColumn('{{%commerce_taxrates}}', 'enabled', $this->boolean()->notNull()->defaultValue(true));
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m241204_045158_enable_tax_rate cannot be reverted.\n";
        return false;
    }
}
