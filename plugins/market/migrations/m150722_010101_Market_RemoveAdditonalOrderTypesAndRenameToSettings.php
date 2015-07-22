<?php
namespace Craft;

class m150722_010101_Market_RemoveAdditonalOrderTypesAndRenameToSettings extends BaseMigration
{
    public function safeUp()
    {

        MigrationHelper::dropForeignKeyIfExists('market_orders',['typeId']);
        MigrationHelper::dropIndexIfExists('market_orders',['typeId']);
        $this->dropColumn('market_orders','typeId');

        // find everything that is not 'order'
        $ids = craft()->db->createCommand()
            ->select('id')
            ->from('market_ordertypes')
            ->where("handle != 'order'")
            ->queryColumn();

        // delete 'em
        $this->delete('market_ordertypes', array('in', 'id', $ids));

        $table = MigrationHelper::getTable('market_ordertypes');
        MigrationHelper::dropAllForeignKeysOnTable($table);
        $this->renameTable('market_ordertypes','market_ordersettings');
        $this->addForeignKey('market_ordersettings','fieldLayoutId','fieldlayouts','id','SET NULL');

        $orderSettings = craft()->db->createCommand()
            ->select('*')
            ->from('market_ordersettings')
            ->where("handle = 'order'")
            ->queryScalar();

        if(!$orderSettings){
            craft()->db->createCommand()
                ->insert('market_ordersettings',[
                    'name'=>'Order',
                    'handle'=>'order',
                    'fieldLayoutId' => null
                ]);
        }

        return true;
    }
}