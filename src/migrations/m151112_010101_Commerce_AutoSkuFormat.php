<?php
namespace Craft;

class m151112_010101_Commerce_AutoSkuFormat extends BaseMigration
{
    public function safeUp()
    {
        $this->addColumnAfter('commerce_producttypes','skuFormat',AttributeType::String,'titleFormat');
    }
}
