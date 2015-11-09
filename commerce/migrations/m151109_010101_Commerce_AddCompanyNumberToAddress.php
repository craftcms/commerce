<?php
namespace Craft;

class m151109_010101_Commerce_AddCompanyNumberToAddress extends BaseMigration
{
    public function safeUp()
    {
        $this->addColumnAfter('commerce_addresses','companyNumber',AttributeType::String,'company');
    }
}
