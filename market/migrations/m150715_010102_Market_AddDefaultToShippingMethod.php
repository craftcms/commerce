<?php
namespace Craft;

class m150715_010102_Market_AddDefaultToShippingMethod extends BaseMigration
{
    public function safeUp()
    {
        $this->addColumnAfter('market_shippingmethods','default',ColumnType::Bool,'enabled');
        MigrationHelper::dropForeignKeyIfExists('market_ordertypes',['shippingMethodId']);
        $this->dropColumn('market_ordertypes','shippingMethodId');
        return true;
    }
}