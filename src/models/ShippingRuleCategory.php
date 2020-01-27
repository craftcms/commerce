<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use craft\commerce\base\Model;
use craft\commerce\Plugin;

/**
 * Shipping rule model
 *
 * @property ShippingCategory $category
 * @property ShippingRule $rule
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class ShippingRuleCategory extends Model
{
    /**
     * @var int ID
     */
    public $id;

    /**
     * @var int Shipping rule ID
     */
    public $shippingRuleId;

    /**
     * @var int Shipping category ID
     */
    public $shippingCategoryId;

    /**
     * @var float Per item rate
     */
    public $perItemRate;

    /**
     * @var float Weight rate
     */
    public $weightRate;

    /**
     * @var float Percentage rate
     */
    public $percentageRate;

    /**
     * @var string Condition
     */
    public $condition;


    /**
     * @inheritdoc
     */
    public function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] =
            [
                ['condition'],
                'in',
                'range' => [
                    'allow',
                    'disallow',
                    'require'
                ],

            ];

        return $rules;
    }

    /**
     * @return ShippingRule
     */
    public function getRule(): ShippingRule
    {
        return Plugin::getInstance()->getShippingRules()->getShippingRuleById($this->shippingRuleId);
    }

    /**
     * @return ShippingCategory
     */
    public function getCategory(): ShippingCategory
    {
        return Plugin::getInstance()->getShippingCategories()->getShippingCategoryById($this->shippingCategoryId);
    }
}
