<?php
namespace Craft;

class m160927_010101_Commerce_ProductTypeShippingTaxCategories extends BaseMigration
{
    public function safeUp()
    {
        // Product Type Shipping Category
        craft()->db->createCommand()->createTable('commerce_producttypes_shippingcategories', array(
            'productTypeId'      => array('column' => 'integer', 'required' => true),
            'shippingCategoryId' => array('column' => 'integer', 'required' => true),
        ), null, true);
        craft()->db->createCommand()->createIndex('commerce_producttypes_shippingcategories', 'productTypeId,shippingCategoryId', true);
        craft()->db->createCommand()->addForeignKey('commerce_producttypes_shippingcategories', 'productTypeId', 'commerce_producttypes', 'id', 'CASCADE', null);
        craft()->db->createCommand()->addForeignKey('commerce_producttypes_shippingcategories', 'shippingCategoryId', 'commerce_shippingcategories', 'id', 'CASCADE', null);

        $shippingCategoryIds = craft()->db->createCommand()->select('id')->from('commerce_shippingcategories')->queryColumn();
        $productTypeIds = craft()->db->createCommand()->select('id')->from('commerce_producttypes')->queryColumn();

        foreach ($shippingCategoryIds as $shippingCategoryId)
        {
            foreach ($productTypeIds as $productTypeId)
            {
                $data = [
                    'shippingCategoryId' => $shippingCategoryId,
                    'productTypeId' => $productTypeId,
                ];
                craft()->db->createCommand()->insert('commerce_producttypes_shippingcategories', $data);
            }
        }

        // Product Type Tax Category
        craft()->db->createCommand()->createTable('commerce_producttypes_taxcategories', array(
            'productTypeId' => array('column' => 'integer', 'required' => true),
            'taxCategoryId' => array('column' => 'integer', 'required' => true),
        ), null, true);
        craft()->db->createCommand()->createIndex('commerce_producttypes_taxcategories', 'productTypeId,taxCategoryId', true);
        craft()->db->createCommand()->addForeignKey('commerce_producttypes_taxcategories', 'productTypeId', 'commerce_producttypes', 'id', 'CASCADE', null);
        craft()->db->createCommand()->addForeignKey('commerce_producttypes_taxcategories', 'taxCategoryId', 'commerce_taxcategories', 'id', 'CASCADE', null);

        $taxCategoryIds = craft()->db->createCommand()->select('id')->from('commerce_taxcategories')->queryColumn();
        $productTypeIds = craft()->db->createCommand()->select('id')->from('commerce_producttypes')->queryColumn();

        foreach ($taxCategoryIds as $taxCategoryId)
        {
            foreach ($productTypeIds as $productTypeId)
            {
                $data = [
                    'taxCategoryId' => $taxCategoryId,
                    'productTypeId' => $productTypeId,
                ];
                craft()->db->createCommand()->insert('commerce_producttypes_taxcategories', $data);
            }
        }
    }
}
