<?php
namespace Craft;

class m150713_010101_Market_AddCachedAddressFieldToOrder extends BaseMigration
{
    public function safeUp()
    {
        $this->addColumnAfter('market_orders','shippingAddressData',ColumnType::Text,'shippingAddressId');
        $this->addColumnAfter('market_orders','billingAddressData',ColumnType::Text,'billingAddressId');

        return true;
    }
}