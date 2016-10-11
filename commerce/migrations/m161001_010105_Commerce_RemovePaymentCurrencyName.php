<?php
namespace Craft;

class m161001_010105_Commerce_RemovePaymentCurrencyName extends BaseMigration
{
    public function safeUp()
    {
        if (craft()->db->columnExists('commerce_paymentcurrencies', 'name')) {
            $this->dropColumn('commerce_paymentcurrencies', 'name');
        }
    }
}
