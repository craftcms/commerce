<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;

/**
 * m230111_112916_update_lineitems_table migration.
 */
class m230111_112916_update_lineitems_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addColumn(Table::LINEITEMS, 'promotionalPrice', $this->decimal(14, 4)->after('price')->null()->unsigned());

        $this->renameColumn(Table::LINEITEMS, 'saleAmount', 'promotionalAmount');

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m230111_112916_update_lineitems_table cannot be reverted.\n";
        return false;
    }
}
