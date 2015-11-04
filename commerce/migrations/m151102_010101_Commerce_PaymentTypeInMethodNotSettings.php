<?php
namespace Craft;

class m151102_010101_Commerce_PaymentTypeInMethodNotSettings extends BaseMigration
{
    public function safeUp()
    {
        $this->addColumnAfter('commerce_paymentmethods','paymentType',"enum('authorize', 'purchase') NOT NULL DEFAULT 'purchase'",'name');
    }
}
