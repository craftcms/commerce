<?php
namespace Craft;

class m150716_010109_Market_PaidTotalsOnOrder extends BaseMigration
{
    public function safeUp()
    {
        $this->addColumnAfter('market_orders','paidTotal',"decimal(14,4) DEFAULT '0.0000'",'finalPrice');
        $this->addColumnAfter('market_orders','paidAt',ColumnType::DateTime,'completedAt');

        return true;
    }
}