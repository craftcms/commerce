<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use Craft;
use craft\base\Element;
use craft\commerce\base\Model;
use craft\commerce\elements\conditions\customers\CatalogPricingRuleCustomerCondition;
use craft\commerce\elements\conditions\purchasables\CatalogPricingRulePurchasableCondition;
use craft\commerce\records\CatalogPricingRule as PricingCatalogRuleRecord;
use craft\elements\conditions\ElementConditionInterface;
use craft\elements\User;
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use DateTime;

/**
 * Catalog Pricing Rule model.
 *
 * @property string|false $cpEditUrl
 * @property string $applyAmountAsFlat
 * @property string $applyAmountAsPercent
 * @property string|array|ElementConditionInterface $customerCondition
 * @property string|array|ElementConditionInterface $purchasableCondition
 * @property array $purchasableIds
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 */
class CatalogPricingRule extends Model
{
    /**
     * @var int|null ID
     */
    public ?int $id = null;

    /**
     * @var string|null Name
     */
    public ?string $name = null;

    /**
     * @var string|null Description
     */
    public ?string $description = null;

    /**
     * @var DateTime|null Date From
     */
    public ?DateTime $dateFrom = null;

    /**
     * @var DateTime|null Date To
     */
    public ?DateTime $dateTo = null;

    /**
     * @var string How the sale should be applied
     */
    public string $apply = PricingCatalogRuleRecord::APPLY_BY_PERCENT;

    /**
     * @var float|null The amount field used by the apply option
     */
    public ?float $applyAmount = null;

    /**
     * @var ElementConditionInterface|null
     * @see getCustomerCondition()
     * @see setCustomerCondition()
     */
    public null|ElementConditionInterface $_customerCondition = null;

    /**
     * @var ElementConditionInterface|null
     * @see getPurchasableCondition()
     * @see setPurchasableCondition()
     */
    public null|ElementConditionInterface $_purchasableCondition = null;

    /**
     * @var bool Enabled
     */
    public bool $enabled = true;

    /**
     * @var bool
     */
    public bool $isPromotionalPrice = false;

    /**
     * @var DateTime|null
     * @since 3.4
     */
    public ?DateTime $dateCreated = null;

    /**
     * @var DateTime|null
     * @since 3.4
     */
    public ?DateTime $dateUpdated = null;

    /**
     * @var int[]|null Product Ids
     */
    private ?array $_purchasableIds = null;

    /**
     * @var int[]|null
     */
    private ?array $_userIds = null;

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return [
            [['apply'], 'in', 'range' => ['toPercent', 'toFlat', 'byPercent', 'byFlat']],
            [['enabled'], 'boolean'],
            [['name', 'apply'], 'required'],
            [['id', 'applyAmount', 'customerCondition', 'dateFrom', 'dateTo', 'isPromotionalPrice', 'purchasableCondition'], 'safe'],
        ];
    }

    public function getCpEditUrl(): string
    {
        return UrlHelper::cpUrl('commerce/promotions/catalog-pricing-rules/' . $this->id);
    }

    /**
     * @return array
     */
    public function extraFields(): array
    {
        $fields = parent::extraFields();
        $fields[] = 'purchasableIds';

        return $fields;
    }

    public function getApplyAmountAsPercent(): string
    {
        return Craft::$app->getFormatter()->asPercent(-($this->applyAmount ?? 0.0));
    }

    public function getApplyAmountAsFlat(): string
    {
        return $this->applyAmount !== null ? (string)($this->applyAmount * -1) : '0';
    }

    /**
     * @return int[]|null
     */
    public function getPurchasableIds(): ?array
    {
        if ($this->_purchasableIds === null && !empty($this->getPurchasableCondition()->getConditionRules())) {
            $purchasableQuery = Element::find();
            $this->getPurchasableCondition()->modifyQuery($purchasableQuery);
            $this->_purchasableIds = $purchasableQuery->ids();
        }

        return $this->_purchasableIds;
    }

    /**
     * @return ElementConditionInterface
     */
    public function getCustomerCondition(): ElementConditionInterface
    {
        $condition = $this->_customerCondition ?? new CatalogPricingRuleCustomerCondition();
        $condition->mainTag = 'div';
        $condition->name = 'customerCondition';

        return $condition;
    }

    /**
     * @param ElementConditionInterface|string|array $condition
     * @return void
     * @throws \yii\base\InvalidConfigException
     */
    public function setCustomerCondition(ElementConditionInterface|string|array $condition): void
    {
        if (is_string($condition)) {
            $condition = Json::decodeIfJson($condition);
        }

        if (!$condition instanceof ElementConditionInterface) {
            $condition['class'] = CatalogPricingRuleCustomerCondition::class;
            $condition = Craft::$app->getConditions()->createCondition($condition);
            /** @var CatalogPricingRuleCustomerCondition $condition */
        }
        $condition->forProjectConfig = false;

        $this->_customerCondition = $condition;
    }

    /**
     * @return ElementConditionInterface
     */
    public function getPurchasableCondition(): ElementConditionInterface
    {
        $condition = $this->_purchasableCondition ?? new CatalogPricingRulePurchasableCondition();
        $condition->mainTag = 'div';
        $condition->name = 'purchasableCondition';

        return $condition;
    }

    /**
     * @param ElementConditionInterface|string|array $condition
     * @return void
     * @throws \yii\base\InvalidConfigException
     */
    public function setPurchasableCondition(ElementConditionInterface|string|array $condition): void
    {
        if (is_string($condition)) {
            $condition = Json::decodeIfJson($condition);
        }

        if (!$condition instanceof ElementConditionInterface) {
            $condition['class'] = CatalogPricingRulePurchasableCondition::class;
            $condition = Craft::$app->getConditions()->createCondition($condition);
            /** @var CatalogPricingRulePurchasableCondition $condition */
        }
        $condition->forProjectConfig = false;

        $this->_purchasableCondition = $condition;
    }

    /**
     * @return int[]|null
     */
    public function getUserIds(): ?array
    {
        if ($this->_userIds === null && !empty($this->getCustomerCondition()->getConditionRules())) {
            $userQuery = User::find();
            $this->getCustomerCondition()->modifyQuery($userQuery);
            $this->_userIds = $userQuery->ids();
        }

        return $this->_userIds;
    }

    /**
     * @param float $price
     * @return float
     */
    public function getRulePriceFromPrice(float $price): float
    {
        $price = match ($this->apply) {
            PricingCatalogRuleRecord::APPLY_BY_PERCENT => $price * (1 + $this->applyAmount),
            PricingCatalogRuleRecord::APPLY_BY_FLAT => $price + $this->applyAmount,
            PricingCatalogRuleRecord::APPLY_TO_PERCENT => $price * -$this->applyAmount,
            PricingCatalogRuleRecord::APPLY_TO_FLAT => -$this->applyAmount,
            default => $price,
        };

        return max($price, 0);
    }
}
