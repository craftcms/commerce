<?php
namespace Craft;

class m150730_010102_Market_AddNoteFieldToLineItem extends BaseMigration
{
    public function safeUp()
    {
        $this->addColumnAfter('market_lineitems','note',ColumnType::Text,'snapshot');
        return true;
    }
}