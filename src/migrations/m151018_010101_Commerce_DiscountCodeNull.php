<?php
namespace Craft;

class m151018_010101_Commerce_DiscountCodeNull extends BaseMigration
{
    public function safeUp()
    {
        Craft()->db->createCommand("
            ALTER TABLE {{commerce_discounts}}
            CHANGE `code` `code` VARCHAR(255) NULL
        ")->execute();
        return true;
    }
}
