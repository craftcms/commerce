<?php
namespace Craft;

class m151109_010102_Commerce_AddOptionsToLineItems extends BaseMigration
{
    public function safeUp()
    {
        $table = MigrationHelper::getTable('commerce_lineitems');

        if(!array_key_exists('options',$table->columns)){
            $this->addColumnAfter('commerce_lineitems','options',ColumnType::Text,'purchasableId');
        }

        if(!array_key_exists('optionsSignature',$table->columns)){
            $this->addColumnAfter('commerce_lineitems','optionsSignature',AttributeType::String,'purchasableId');
        }

        // make a signature of an empty array
        $blankSig = [];
        ksort($blankSig);
        $signature = md5(json_encode($blankSig));
        craft()->db->createCommand()->update('commerce_lineitems', ['options'=>json_encode([]),'optionsSignature'=>$signature]);

        MigrationHelper::dropAllForeignKeysOnTable($table);
        MigrationHelper::dropAllUniqueIndexesOnTable($table);

        $this->createIndex('commerce_lineitems','orderId,purchasableId,optionsSignature',true);

        $this->addForeignKey('commerce_lineitems','orderId','commerce_orders','id','CASCADE');
        $this->addForeignKey('commerce_lineitems','purchasableId','elements','id','SET NULL');
        $this->addForeignKey('commerce_lineitems','taxCategoryId','commerce_taxcategories','id','RESTRICT');


        return true;
    }
}
