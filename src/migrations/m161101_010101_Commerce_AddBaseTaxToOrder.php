<?php
namespace Craft;

class m161101_010101_Commerce_AddBaseTaxToOrder extends BaseMigration
{
    public function safeUp()
    {
      $this->addColumnAfter('commerce_orders','baseTax',array('maxLength' => 10, 'decimals' => 4, 'default' => 0, 'unsigned' => false, 'length' => 14, 'column' => 'decimal'),'baseShippingCost');
    }
}
