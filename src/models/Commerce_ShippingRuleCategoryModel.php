<?php
namespace Craft;

/**
 * Shipping rule model
 *
 * @property int $id
 * @property int $shippingRuleId
 * @property int $shippingCategoryId
 * @property bool $enabled
 * @property string $condition
 * @property float $perItemRate
 * @property float $weightRate
 * @property float $percentageRate
 *
 * @property Commerce_ShippingRuleRecord $shippingRule
 * @property Commerce_ShippingCategoryRecord $shippingCategory
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.models
 * @since     1.2
 */
class Commerce_ShippingRuleCategoryModel extends BaseModel
{

    public function getRule()
    {
        return craft()->commerce_shippingRules->getShippingRuleById($this->shippingRuleId);
    }

    public function getCategory()
    {
        return craft()->commerce_shippingCategories->getShippingCategoryById($this->shippingCategoryId);
    }

    /**
     * @return array
     */
    protected function defineAttributes()
    {
        return [
            'shippingRuleId' => AttributeType::Number,
            'shippingCategoryId' => AttributeType::Number,
            'condition' => array(AttributeType::Enum, 'values' => [Commerce_ShippingRuleCategoryRecord::CONDITION_ALLOW, Commerce_ShippingRuleCategoryRecord::CONDITION_DISALLOW, Commerce_ShippingRuleCategoryRecord::CONDITION_REQUIRE], 'required' => true),
            'perItemRate'    => [
                AttributeType::Number,
                'required' => false,
                'decimals' => 4,
                'default' => null
            ],
            'weightRate'     => [
                AttributeType::Number,
                'required' => false,
                'decimals' => 4,
                'default' => null
            ],
            'percentageRate'     => [
                AttributeType::Number,
                'required' => false,
                'decimals' => 4,
                'default' => null
            ],
        ];
    }
}
