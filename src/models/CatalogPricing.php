<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use craft\commerce\base\Model;
use craft\commerce\base\StoreTrait;
use craft\commerce\Plugin;
use yii\base\InvalidConfigException;

/**
 * Catalog Pricing model.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 */
class CatalogPricing extends Model
{
    use StoreTrait;

    /**
     * @var int|null
     */
    public ?int $id = null;

    /**
     * @var int|null
     */
    public ?int $purchasableId = null;

    /**
     * @var float|null
     */
    public ?float $price = null;

    /**
     * @var int|null
     */
    public ?int $catalogPricingRuleId = null;

    /**
     * @var \DateTime|null
     */
    public ?\DateTime $dateFrom = null;

    /**
     * @var \DateTime|null
     */
    public ?\DateTime $dateTo = null;

    /**
     * @var bool
     */
    public bool $isPromotionalPrice = false;

    /**
     * @var string|null
     */
    public ?string $uid = null;

    /**
     * @var CatalogPricingRule|null
     */
    private ?CatalogPricingRule $_catalogPricingRule = null;

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [[
            'id',
            'purchasableId',
            'price',
            'catalogPricingRuleId',
            'dateFrom',
            'dateTo',
            'isPromotionalPrice',
            'uid',
        ], 'safe'];

        return $rules;
    }

    /**
     * @return CatalogPricingRule|null
     * @throws InvalidConfigException
     */
    public function getCatalogPricingRule(): ?CatalogPricingRule
    {
        if ($this->_catalogPricingRule !== null) {
            return $this->_catalogPricingRule;
        }

        if (!$this->catalogPricingRuleId) {
            return null;
        }

        $this->_catalogPricingRule = Plugin::getInstance()->getCatalogPricingRules()->getCatalogPricingRuleById($this->catalogPricingRuleId);

        return $this->_catalogPricingRule;
    }
}