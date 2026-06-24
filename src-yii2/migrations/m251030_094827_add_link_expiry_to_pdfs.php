<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;

/**
 * m251030_094827_add_link_expiry_to_pdfs migration.
 */
class m251030_094827_add_link_expiry_to_pdfs extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addColumn(Table::PDFS, 'linkExpiry', $this->integer()->notNull()->defaultValue(86400)->after('language'));

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m251030_094827_add_link_expiry_to_pdfs cannot be reverted.\n";
        return false;
    }
}
