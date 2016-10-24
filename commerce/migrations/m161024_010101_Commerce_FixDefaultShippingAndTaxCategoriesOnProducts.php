<?php
namespace Craft;

class m161024_010101_Commerce_FixDefaultShippingAndTaxCategoriesOnProducts extends BaseMigration
{
    public function safeUp()
    {
        // Ensure the shipping category set on the products is available to the that product's type
        $productTypeTaxCategories = craft()->db->createCommand()->select("productTypeId, GROUP_CONCAT(taxCategoryId SEPARATOR ',') as taxCategoryIds")->from('commerce_producttypes_taxcategories')->group('productTypeId')->queryAll();
        foreach ($productTypeTaxCategories as $key => $productTypeTaxCategory)
        {
            $productTypeTaxCategories[$key]['taxCategoryIds'] = explode(',',$productTypeTaxCategory['taxCategoryIds']);

            $data = ['taxCategoryId' => $productTypeTaxCategories[$key]['taxCategoryIds'][0]];
            $condition = [
                'and',
                [
                    'typeId = '.$productTypeTaxCategories[$key]['productTypeId'],
                    ['not in', 'taxCategoryId', $productTypeTaxCategories[$key]['taxCategoryIds']]
                ]
            ];
            craft()->db->createCommand()->update('commerce_products', $data, $condition);
        }

        // Ensure the shipping category set on the products is available to the that product's type
        $productTypeShippingCategories = craft()->db->createCommand()->select("productTypeId, GROUP_CONCAT(shippingCategoryId SEPARATOR ',') as shippingCategoryIds")->from('commerce_producttypes_shippingcategories')->group('productTypeId')->queryAll();
        foreach ($productTypeShippingCategories as $key => $productTypeShippingCategory)
        {
            $productTypeShippingCategories[$key]['shippingCategoryIds'] = explode(',', $productTypeShippingCategory['shippingCategoryIds']);

            $data = ['shippingCategoryId' => $productTypeShippingCategories[$key]['shippingCategoryIds'][0]];
            $condition = [
                'and',
                [
                    'typeId = '.$productTypeShippingCategories[$key]['productTypeId'],
                    ['not in', 'shippingCategoryId', $productTypeShippingCategories[$key]['shippingCategoryIds']]
                ]
            ];
            craft()->db->createCommand()->update('commerce_products', $data, $condition);
        }
    }
}
