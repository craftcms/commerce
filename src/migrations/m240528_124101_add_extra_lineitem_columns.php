<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;

/**
 * m240528_124101_add_extra_lineitem_columns migration.
 */
class m240528_124101_add_extra_lineitem_columns extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addColumn(Table::LINEITEMS, 'hasFreeShipping', $this->boolean());

        $this->addColumn(Table::LINEITEMS, 'isPromotable', $this->boolean());

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m240528_124101_add_extra_lineitem_columns cannot be reverted.\n";
        return false;
    }
}
