<?php
namespace Craft;

class m160917_010104_Commerce_ShippingCategories extends BaseMigration
{
    public function safeUp()
    {

        if(!MigrationHelper::getTable('commerce_shippingcategories'))
        {
            craft()->db->createCommand()->createTable('commerce_shippingcategories', ['name'        => ['required' => true],
                                                                                      'handle'      => ['required' => true],
                                                                                      'description' => [],
                                                                                      'default'     => ['maxLength' => 1,
                                                                                                        'default'   => 0,
                                                                                                        'required'  => true,
                                                                                                        'column'    => 'tinyint',
                                                                                                        'unsigned'  => true],], null, true);

            craft()->db->createCommand()->createIndex('commerce_shippingcategories', 'handle', true);

            $data = [];
            $data['name'] = 'General';
            $data['handle'] = 'general';
            $data['description'] = 'General shipping category';
            $data['default'] = 1;

            craft()->db->createCommand()->insert('commerce_shippingcategories', $data);
        }

        $this->addColumnAfter('commerce_products','shippingCategoryId',['column' => 'integer', 'required' => true],'taxCategoryId');

        $table = MigrationHelper::getTable('commerce_products');

        // Get rid of any orphaned product elements.
        $productIds = craft()->db->createCommand()->select('id')->from('commerce_products')->queryColumn();
        foreach($productIds as $id)
        {
            $element = craft()->db->createCommand()->select('id')->from('elements')->where(['id'=> $id])->queryScalar();

            if (!$element)
            {
                craft()->db->createCommand()->delete('commerce_products','id = :xid',[':xid'=>$id]);
            }
        }

        MigrationHelper::dropAllForeignKeysOnTable($table);
        MigrationHelper::dropAllIndexesOnTable($table);

        $data = [];
        $data['shippingCategoryId'] = 1;
        craft()->db->createCommand()->update('commerce_products', $data);

        craft()->db->createCommand()->createIndex('commerce_products', 'typeId', false);
        craft()->db->createCommand()->createIndex('commerce_products', 'postDate', false);
        craft()->db->createCommand()->createIndex('commerce_products', 'expiryDate', false);

        craft()->db->createCommand()->addForeignKey('commerce_products', 'id', 'elements', 'id', 'CASCADE', null);
        craft()->db->createCommand()->addForeignKey('commerce_products', 'typeId', 'commerce_producttypes', 'id', 'CASCADE', null);
        craft()->db->createCommand()->addForeignKey('commerce_products', 'taxCategoryId', 'commerce_taxcategories', 'id', null, null);
        craft()->db->createCommand()->addForeignKey('commerce_products', 'shippingCategoryId', 'commerce_shippingcategories', 'id', null, null);

    }
}
