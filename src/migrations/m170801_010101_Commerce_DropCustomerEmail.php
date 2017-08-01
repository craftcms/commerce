<?php

namespace Craft;

class m170801_010101_Commerce_DropCustomerEmail extends BaseMigration
{
    public function safeUp()
    {
        $this->dropColumn('commerce_customers','email');
    }
}
