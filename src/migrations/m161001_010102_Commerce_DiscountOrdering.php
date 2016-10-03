<?php
namespace Craft;

class m161001_010102_Commerce_DiscountOrdering extends BaseMigration
{
    public function safeUp()
    {
        $this->addColumnAfter('commerce_discounts','sortOrder',array(AttributeType::Number, 'column' => ColumnType::Int),'enabled');
    }
}
