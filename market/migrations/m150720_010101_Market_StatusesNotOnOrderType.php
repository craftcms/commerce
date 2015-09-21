<?php
namespace Craft;

class m150720_010101_Market_StatusesNotOnOrderType extends BaseMigration
{
    public function safeUp()
    {
        MigrationHelper::dropForeignKeyIfExists('market_orderstatuses',['orderTypeId']);
        $this->dropColumn('market_orderstatuses','orderTypeId');
        return true;
    }
}