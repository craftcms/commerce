<?php
namespace Craft;

class m150418_010101_Market_AddMessageToOrder extends BaseMigration
{
    public function safeUp()
    {
        // Allow transforms to have a format
        $this->addColumnAfter('market_orders', 'message',
            [ColumnType::Varchar, 'required' => false], 'lastIp');

        return true;
    }
}