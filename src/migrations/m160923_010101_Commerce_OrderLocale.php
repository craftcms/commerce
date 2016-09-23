<?php
namespace Craft;

class m160923_010101_Commerce_OrderLocale extends BaseMigration
{
    public function safeUp()
    {
        $this->addColumnAfter('commerce_orders','orderLocale',AttributeType::String,'lastIp');
    }
}
