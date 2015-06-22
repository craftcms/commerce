<?php
namespace Craft;

class m150619_010101_Market_AddMaxQtyToVariant extends BaseMigration
{
    public function safeUp()
    {
        $this->addColumnAfter('market_variants', 'maxQty', ColumnType::Int,
            'minQty');

        return true;
    }
}