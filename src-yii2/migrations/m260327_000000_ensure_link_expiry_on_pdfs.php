<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;

/**
 * m260327_000000_ensure_link_expiry_on_pdfs migration.
 */
class m260327_000000_ensure_link_expiry_on_pdfs extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if (!$this->db->columnExists(Table::PDFS, 'linkExpiry')) {
            $this->addColumn(Table::PDFS, 'linkExpiry', $this->integer()->notNull()->defaultValue(86400)->after('language'));
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m260327_000000_ensure_link_expiry_on_pdfs cannot be reverted.\n";
        return false;
    }
}
