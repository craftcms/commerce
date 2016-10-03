<?php
namespace Craft;

class m161001_010101_Commerce_LineItemShippingCat extends BaseMigration
{
    public function safeUp()
    {
        $this->addColumnAfter('commerce_lineitems','shippingCategoryId',array(AttributeType::Number, 'column' => ColumnType::Int),'taxCategoryId');
        craft()->db->createCommand()->update('commerce_lineitems', ['shippingCategoryId' => 1]);
    }
}
