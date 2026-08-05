<?php

use craft\commerce\db\Table;
use craft\db\Migration;

return new class extends Migration {
    public function safeUp(): bool
    {
        if (!$this->db->columnExists(Table::ORDERNOTICES, 'noticeType')) {
            $this->addColumn(Table::ORDERNOTICES, 'noticeType', $this->string()->notNull()->defaultValue('customer'));
        }

        return true;
    }

    public function safeDown(): bool
    {
        if ($this->db->columnExists(Table::ORDERNOTICES, 'noticeType')) {
            $this->dropColumn(Table::ORDERNOTICES, 'noticeType');
        }

        return true;
    }
};
