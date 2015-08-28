<?php
namespace Craft;

class m150820_010101_Market_FixProductAndVariants extends BaseMigration
{
    public function safeUp()
    {

        // Find any elements in the craft_elements table that don't exist in our market records and remove them.
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