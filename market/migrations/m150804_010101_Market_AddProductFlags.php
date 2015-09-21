<?php
namespace Craft;

class m150804_010101_Market_AddProductFlags extends BaseMigration
{
    public function safeUp()
    {
        $this->addColumnAfter('market_products','promotable',ColumnType::Bool,'typeId');
        $this->addColumnAfter('market_products','freeShipping',ColumnType::Bool,'typeId');

        return true;
    }
}