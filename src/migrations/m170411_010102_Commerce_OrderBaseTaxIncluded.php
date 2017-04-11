<?php
namespace Craft;

class m170411_010102_Commerce_OrderBaseTaxIncluded extends BaseMigration
{
    public function safeUp()
    {
        $this->addColumnAfter('commerce_orders','baseTaxIncluded',array('maxLength' => 10, 'decimals' => 4, 'default' => 0, 'unsigned' => false, 'length' => 14, 'column' => 'decimal'),'baseTax');
    }
}
