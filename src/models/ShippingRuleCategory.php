<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use craft\commerce\base\Model;
use craft\commerce\Plugin;
use yii\base\InvalidConfigException;

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
     * @var int|null ID
     */
    public ?int $id = null;

    /**
     * @var int Shipping rule ID
     */
    public int $shippingRuleId;

    /**
     * @var int Shipping category ID
     */
    public int $shippingCategoryId;

    /**
     * @var float|null Per item rate
     */
    public ?float $perItemRate = null;

    /**
     * @var float|null Weight rate
     */
    public ?float $weightRate = null;

    /**
     * @var float|null Percentage rate
     */
    public ?float $percentageRate = null;

    /**
     * @var string Condition
     */
    public string $condition;

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return [
            [['condition'], 'in', 'range' => ['allow', 'disallow', 'require']],
            [['perItemRate', 'weightRate', 'percentageRate'], 'number', 'skipOnEmpty' => true],
            [
                [
                    'shippingRuleId',
                    'shippingCategoryId',
                    'condition',
                    'perItemRate',
                    'weightRate',
                    'percentageRate',
                ],
                'safe',
            ],
        ];
    }

    /**
     * @return ShippingRule
     * @throws InvalidConfigException
     */
    public function getRule(): ShippingRule
    {
        return Plugin::getInstance()->getShippingRules()->getShippingRuleById($this->shippingRuleId);
    }

    /**
     * @return ShippingCategory
     * @throws InvalidConfigException
     */
    public function getCategory(): ShippingCategory
    {
        return Plugin::getInstance()->getShippingCategories()->getShippingCategoryById($this->shippingCategoryId);
    }
}
