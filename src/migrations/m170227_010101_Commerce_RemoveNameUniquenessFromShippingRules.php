<?php
namespace Craft;

class m170227_010101_Commerce_RemoveNameUniquenessFromShippingRules extends BaseMigration
{
    public function safeUp()
    {
        $table = MigrationHelper::getTable('commerce_shippingrules');

        MigrationHelper::dropAllForeignKeysOnTable($table);
        
        MigrationHelper::dropIndexIfExists('commerce_shippingrules',['methodId']);
        MigrationHelper::dropIndexIfExists('commerce_shippingrules',['name']);

        craft()->db->createCommand()->createIndex('commerce_shippingrules', 'name', false);
        craft()->db->createCommand()->createIndex('commerce_shippingrules', 'methodId', false);

        craft()->db->createCommand()->addForeignKey('commerce_shippingrules', 'shippingZoneId', 'commerce_shippingzones', 'id', 'SET NULL', null);
        craft()->db->createCommand()->addForeignKey('commerce_shippingrules', 'methodId', 'commerce_shippingmethods', 'id', null, null);
    }
}
