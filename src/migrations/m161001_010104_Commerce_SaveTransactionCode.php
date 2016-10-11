<?php
namespace Craft;

class m161001_010104_Commerce_SaveTransactionCode extends BaseMigration
{
    public function safeUp()
    {
        $this->addColumnAfter('commerce_transactions','code',ColumnType::Varchar,'message');
    }
}
