<?php

namespace Craft;

class m170727_010101_Commerce_AddPercentageOffOption extends BaseMigration
{
    public function safeUp()
    {
        $this->addColumnAfter('commerce_discounts','percentageOffSubject',"enum('original', 'discounted') NOT NULL DEFAULT 'discounted'",'percentDiscount');

        craft()->db->createCommand()->update('commerce_discounts', ['percentageOffSubject' => 'original']);
    }
}
