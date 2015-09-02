<?php
namespace Craft;

class m150820_010101_Market_FixProductAndVariants extends BaseMigration
{
    public function safeUp()
    {

        $productIds = $craft->db->createCommand()->select('id')->from("market_products")->queryColumn();
        $variantProductIds = $craft->db->createCommand()->select('productId')->from("market_variants")->queryColumn();

        $variantProductIds = array_unique($variantProductIds);

        foreach($variantProductIds as $vId){
            if (!in_array($vId,$productIds)){
                Craft::log("Deleting variant with productId: ". $vId);
                craft()->db->createCommand()->delete('market_variants', 'productId=:id', array(':id'=>$vId));
            }
        }

        $types = ['Market_Product','Market_Variant','Market_Order'];
        foreach($types as $type){
            $elements = craft()->db->createCommand()->select('id')->from('elements')->where('type = :type',[':type'=>$type])->queryColumn();
            $tableName = strtolower($type)."s";
            $marketTableElements = craft()->db->createCommand()->select('id')->from($tableName)->queryColumn();

            $count = 0;
            foreach($elements as $p){
                if (!in_array($p,$marketTableElements)){
                    Craft::log("Deleting ".$type." element not in market table id: ". $p);
                    craft()->db->createCommand()->delete('elements', 'id=:id', array(':id'=>$p));
                    $count++;
                }
            }
            Craft::log("Total ".$type." elements removed as they are not in market tables: ". $count);
        }

        $table = craft()->db->schema->getTable('craft_market_variants');
        if(isset($table->columns['deletedAt'])) {
            $this->dropColumn('market_variants', 'deletedAt');
        }

        return true;
    }
}