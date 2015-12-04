<?php
namespace Craft;

class m151127_010101_Commerce_TaxRateTaxableOptions extends BaseMigration
{
    public function safeUp()
    {
      $this->addColumnAfter('commerce_taxrates','taxable',"enum('price','shipping','price_shipping') NOT NULL",'include');

        $data = ['taxable'=>'price'];
        craft()->db->createCommand()->update('commerce_taxrates', $data);
    }
}
