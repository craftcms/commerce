<?php
namespace Craft;

class m160927_010101_Commerce_ShippingRuleCategories extends BaseMigration
{
    public function safeUp()
    {
        craft()->db->createCommand()->createTable('commerce_shippingrule_categories', array(
            'shippingRuleId'     => array('column' => 'integer', 'required' => false),
            'shippingCategoryId' => array('column' => 'integer', 'required' => false),
            'condition'          => array('values' => array('allow', 'disallow', 'require'), 'column' => 'enum', 'required' => true),
            'perItemRate'        => array('maxLength' => 10, 'decimals' => 4, 'required' => false, 'default' => null, 'unsigned' => false, 'length' => 14, 'column' => 'decimal'),
            'weightRate'         => array('maxLength' => 10, 'decimals' => 4, 'required' => false, 'default' => null, 'unsigned' => false, 'length' => 14, 'column' => 'decimal'),
            'percentageRate'     => array('maxLength' => 10, 'decimals' => 4, 'required' => false, 'default' => null, 'unsigned' => false, 'length' => 14, 'column' => 'decimal'),
        ), null, true);

        craft()->db->createCommand()->createIndex('commerce_shippingrule_categories', 'shippingRuleId', false);
        craft()->db->createCommand()->createIndex('commerce_shippingrule_categories', 'shippingCategoryId', false);

        craft()->db->createCommand()->addForeignKey('commerce_shippingrule_categories', 'shippingRuleId', 'commerce_shippingrules', 'id', 'CASCADE', null);
        craft()->db->createCommand()->addForeignKey('commerce_shippingrule_categories', 'shippingCategoryId', 'commerce_shippingcategories', 'id', 'CASCADE', null);

        $shippingCategoryIds = craft()->db->createCommand()->select('id')->from('commerce_shippingcategories')->queryColumn();
        $shippingRuleIds = craft()->db->createCommand()->select('id')->from('commerce_shippingrules')->queryColumn();

        foreach($shippingRuleIds as $ruleId)
        {
            foreach ($shippingCategoryIds as $shippingCategoryId)
            {
                $data = [
                    'shippingCategoryId' => $shippingCategoryId,
                    'shippingRuleId' => $ruleId,
                    'condition' => 'allow',
                    'perItemRate' => null,
                    'weightRate' => null,
                    'percentageRate' => null,
                ];
                craft()->db->createCommand()->insert('commerce_shippingrule_categories', $data);
            }
        }


    }
}
