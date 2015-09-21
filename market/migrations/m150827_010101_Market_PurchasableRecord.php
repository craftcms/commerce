<?php
namespace Craft;

class m150827_010101_Market_PurchasableRecord extends BaseMigration
{
    public function safeUp()
    {
        $this->dropTableIfExists('market_purchasable');
        $this->dropTableIfExists('market_purchasables');

        // Create the craft_market_purchasable table
        craft()->db->createCommand()->createTable('market_purchasables', array(
            'id'    => array('column' => 'integer', 'required' => true, 'primaryKey' => true),
            'sku'   => array('required' => true),
            'price' => array('maxLength' => 10, 'decimals' => 4, 'required' => true, 'unsigned' => false, 'length' => 14, 'column' => 'decimal'),
        ), null, false);

        // Add indexes to craft_market_purchasable
        craft()->db->createCommand()->createIndex('market_purchasables', 'sku', true);

        // Add foreign keys to craft_market_purchasable
        craft()->db->createCommand()->addForeignKey('market_purchasables', 'id', 'elements', 'id', 'CASCADE', null);

        $variants = craft()->db->createCommand()->select('*')->from('market_variants')->queryAll();

        foreach($variants as $variant){
            $id = $variant['id'];
            $price = $variant['price'];
            $sku = $variant['sku'];
            craft()->db->createCommand()->insert('market_purchasables',['id'=>$id,'price'=>$price,'sku'=>$sku]);
        }

        return true;
    }
}