<?php

namespace craft\commerce\models;

use craft\commerce\base\Model;
use craft\commerce\Plugin;

/**
 * Shipping rule model
 *
 * @property ShippingRule     $shippingRule
 * @property ShippingCategory $category
 * @property ShippingRule     $rule
 * @property ShippingCategory $shippingCategory
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.models
 * @since     1.2
 */
class ShippingRuleCategory extends Model
{
    /**
     * @var int Category id
     */
    public $id;

    /**
     * @var int Rule ID
     */
    public $shippingRuleId;

    /**
     * @var int Category ID
     */
    public $shippingCategoryId;

    /**
     * @var float Per item rate
     */
    public $perItemRate;

    /**
     * @var float Weight Rate
     */
    public $weightRate;

    /**
     * @var float Percentage Rate
     */
    public $percentageRate;

    /**
     * @var string Condition
     */
    public $condition;

    public function rules(): array
    {
        return [
            [
                ['condition'],
                'in',
                'range' => [
                    'allow',
                    'disallow',
                    'require'
                ],
            ],
        ];
    }

    /**
     * @return ShippingRule
     */
    public function getRule()
    {
        return Plugin::getInstance()->getShippingRules()->getShippingRuleById($this->shippingRuleId);
    }

    /**
     * @return ShippingCategory
     */
    public function getCategory()
    {
        return Plugin::getInstance()->getShippingCategories()->getShippingCategoryById($this->shippingCategoryId);
    }
}
