<?php
namespace Craft;

/**
 * Shipping rule category record.
 *
 * @property int $id
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
 * @package   craft.plugins.commerce.records
 * @since     1.2
 */
class Commerce_ShippingRuleCategoryRecord extends BaseRecord
{
    const CONDITION_ALLOW  = 'allow';
    const CONDITION_DISALLOW = 'disallow';
    const CONDITION_REQUIRE = 'require';

    /**
     * @return string
     */
    public function getTableName()
    {
        return 'commerce_shippingrule_categories';
    }

    /**
     * @return array
     */
    public function defineIndexes()
    {
        return [
            ['columns' => ['shippingRuleId']],
            ['columns' => ['shippingCategoryId']]
        ];
    }

    /**
     * @return array
     */
    public function defineRelations()
    {
        return [
            'shippingRule' => [self::BELONGS_TO, 'Commerce_ShippingRuleRecord', 'onDelete' => static::CASCADE],
            'shippingCategory' => [self::BELONGS_TO, 'Commerce_ShippingCategoryRecord', 'onDelete' => static::CASCADE]
        ];
    }

    /**
     * @return array
     */
    protected function defineAttributes()
    {
        return [
            'condition' => array(AttributeType::Enum, 'values' => array(static::CONDITION_ALLOW, static::CONDITION_DISALLOW, static::CONDITION_REQUIRE), 'required' => true),
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