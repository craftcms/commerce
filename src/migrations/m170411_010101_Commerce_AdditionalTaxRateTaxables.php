<?php
namespace Craft;

class m170411_010101_Commerce_AdditionalTaxRateTaxables extends BaseMigration
{
    public function safeUp()
    {
        $this->alterColumn('commerce_taxrates','taxable',"enum('price','shipping','price_shipping','order_total_shipping','order_total_price') NOT NULL");
    }
}
