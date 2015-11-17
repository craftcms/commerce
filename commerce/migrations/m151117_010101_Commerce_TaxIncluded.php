<?php
namespace Craft;

class m151117_010101_Commerce_TaxIncluded extends BaseMigration
{
    public function safeUp()
    {
        $this->addColumnAfter('commerce_lineitems','taxIncluded',"decimal(14,4) NOT NULL DEFAULT '0.0000'",'tax');
    }
}
