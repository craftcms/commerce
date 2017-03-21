<?php
namespace craft\commerce\base\Model;

use craft\commerce\base\Model;

/**
 * Shipping rule model
 *
 * @property Commerce_ShippingRuleRecord     $shippingRule
 * @property Commerce_ShippingCategoryRecord $shippingCategory
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

    public function rules()
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
     * @return \craft\commerce\models\ShippingRule
     */
    public function getRule()
    {
        return craft()->commerce_shippingRules->getShippingRuleById($this->shippingRuleId);
    }

    /**
     * @return \craft\commerce\models\ShippinCategory
     */
    public function getCategory()
    {
        return craft()->commerce_shippingCategories->getShippingCategoryById($this->shippingCategoryId);
    }

}
