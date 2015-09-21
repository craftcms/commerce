<?php
namespace Craft;

class m150815_010101_Market_AddLastUsedAddressesToCustomer extends BaseMigration
{
    public function safeUp()
    {
        $this->addColumnAfter('market_customers','lastUsedBillingAddressId',ColumnType::Int,'email');
        $this->addColumnAfter('market_customers','lastUsedShippingAddressId',ColumnType::Int,'email');
        return true;
    }
}