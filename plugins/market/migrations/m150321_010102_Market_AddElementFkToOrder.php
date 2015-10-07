<?php
namespace Craft;

class m150321_010102_Market_AddElementFkToOrder extends BaseMigration
{
    public function safeUp()
    {
        $this->addForeignKey('market_orders', 'id', 'elements', 'id',
            'CASCADE');

        return true;
    }
}