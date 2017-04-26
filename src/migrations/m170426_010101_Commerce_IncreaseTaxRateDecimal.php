<?php
namespace Craft;

class m170426_010101_Commerce_IncreaseTaxRateDecimal extends BaseMigration
{
    public function safeUp()
    {
        $this->alterColumn('commerce_taxrates','rate','decimal(14,10)');
    }
}
