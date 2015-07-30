<?php
namespace Craft;

class m1507230_010101_Market_RenameLineItemAndOrderAttributes extends BaseMigration
{
    public function safeUp()
    {
        $this->renameColumn('market_lineitems','taxAmount','tax');
        $this->renameColumn('market_lineitems','shippingAmount','shippingCost');
        $this->renameColumn('market_lineitems','discountAmount','discount');
        $this->renameColumn('market_lineitems','optionsJson','snapshot');
        $this->renameColumn('market_orders','baseShippingRate','baseShippingCost');
        $this->renameColumn('market_orders','finalPrice','totalPrice');
        $this->renameColumn('market_orders','paidTotal','totalPaid');
        $this->renameColumn('market_orders','completedAt','dateOrdered');
        $this->renameColumn('market_orders','paidAt','datePaid');
        
        return true;
    }
}