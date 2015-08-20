<?php
namespace Craft;

class m150820_010101_Market_FixProductAndVariants extends BaseMigration
{
    public function safeUp()
    {

        $products = craft()->db->createCommand()->select('id')->from('elements')->where('type = :type',[':type'=>'Market_Product'])->queryColumn();
        $marketProducts = craft()->db->createCommand()->select('id')->from('market_products')->queryColumn();

        $count = 0;
        foreach($products as $p){
            if (!in_array($p,$marketProducts)){
                Craft::log("Deleting element not in market product table: ". $p);
                craft()->db->createCommand()->delete('elements', 'id=:id', array(':id'=>$p));
                $count++;
            }
        }
        Craft::log("Total product elements removed as they are not in market products table: ". $count);

        $this->dropColumn('market_variants','deletedAt');
        MigrationHelper::dropForeignKeyIfExists('market_variants',['id']);
        $this->addForeignKey('market_variants','id','elements','id','CASCADE');

        return true;
    }
}