<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use Craft;
use craft\commerce\base\HasStoreInterface;
use craft\commerce\base\Model;
use craft\commerce\base\ShippingRuleInterface;
use craft\commerce\base\StoreTrait;
use craft\commerce\elements\conditions\orders\ShippingRuleOrderCondition;
use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use craft\commerce\records\ShippingRuleCategory as ShippingRuleCategoryRecord;
use craft\helpers\Json;
use DateTime;
use yii\base\InvalidConfigException;

/**
 * Shipping rule model
 *
 * @property bool $isEnabled whether this shipping rule enabled for listing and selection
 * @property array $options
 * @property array|ShippingRuleCategory[] $shippingRuleCategories
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class ShippingRule extends Model implements ShippingRuleInterface, HasStoreInterface
{
    use StoreTrait;

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
     * @var int|null Shipping method ID
     */
    public ?int $methodId = null;

    /**
     * @var int Priority
     */
    public int $priority = 0;

    /**
     * @var bool Enabled
     */
    public bool $enabled = true;

    /**
     * @var string|null Order Condition Formula
     */
    public ?string $orderConditionFormula = '';

    /**
     * @var float Base rate
     */
    public float $baseRate = 0;

    /**
     * @var float Per item rate
     */
    public float $perItemRate = 0;

    /**
     * @var float Percentage rate
     */
    public float $percentageRate = 0;

    /**
     * @var float Weight rate
     */
    public float $weightRate = 0;

    /**
     * @var float Minimum Rate
     */
    public float $minRate = 0;

    /**
     * @var float Maximum rate
     */
    public float $maxRate = 0;

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
     * @var ShippingRuleCategory[]|null
     */
    private ?array $_shippingRuleCategories = null;

    /**
     * @var ShippingRuleOrderCondition|null
     * @see setOrderCondition()
     * @see getOrderCondition()
     * @since 5.0.0
     */
    private ?ShippingRuleOrderCondition $_orderCondition = null;

    /**
     * @throws InvalidConfigException
     */
    private function _getUniqueCategoryIdsInOrder(Order $order): array
    {
        $orderShippingCategories = [];
        foreach ($order->getLineItems() as $lineItem) {
            // Don't look at the shipping category of non-shippable products.
            if (!$lineItem->getIsShippable()) {
                continue;
            }

            $orderShippingCategories[] = $lineItem->shippingCategoryId;
        }

        return array_unique($orderShippingCategories);
    }

    /**
     * @param $shippingRuleCategories
     * @return array
     */
    private function _getRequiredAndDisallowedCategoriesFromRule($shippingRuleCategories): array
    {
        $disallowedCategories = [];
        $requiredCategories = [];
        foreach ($shippingRuleCategories as $ruleCategory) {
            if ($ruleCategory->condition === ShippingRuleCategoryRecord::CONDITION_DISALLOW) {
                $disallowedCategories[] = $ruleCategory->shippingCategoryId;
            }

            if ($ruleCategory->condition === ShippingRuleCategoryRecord::CONDITION_REQUIRE) {
                $requiredCategories[] = $ruleCategory->shippingCategoryId;
            }
        }
        return [$disallowedCategories, $requiredCategories];
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return [
            [
                [
                    'name',
                    'methodId',
                    'priority',
                    'enabled',
                    'baseRate',
                    'perItemRate',
                    'weightRate',
                    'percentageRate',
                    'minRate',
                    'maxRate',
                ],
                'required',
            ],
            [['perItemRate', 'weightRate', 'percentageRate'], 'number'],
            [['shippingRuleCategories'], 'validateShippingRuleCategories', 'skipOnEmpty' => true],
            [['orderConditionFormula'], 'string', 'length' => [1, 65000], 'skipOnEmpty' => true],
            [
                'orderConditionFormula',
                function($attribute) {
                    if ($this->{$attribute}) {
                        $order = Order::find()->one();
                        if (!$order) {
                            $order = new Order();
                        }
                        $orderConditionParams = [
                            'order' => $order->toArray([], ['lineItems.snapshot', 'shippingAddress', 'billingAddress']),
                        ];
                        if (!Plugin::getInstance()->getFormulas()->validateConditionSyntax($this->{$attribute}, $orderConditionParams)) {
                            $this->addError($attribute, Craft::t('commerce', 'Invalid order condition syntax.'));
                        }
                    }
                },
            ],
            [['id', 'orderCondition', 'storeId'], 'safe'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function extraFields(): array
    {
        $fields = parent::extraFields();
        $fields[] = 'shippingRuleCategories';

        return $fields;
    }

    /**
     * @inheritdoc
     */
    public function getIsEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @param ShippingRuleOrderCondition|string|array|null $condition
     * @return void
     * @throws InvalidConfigException
     * @since 5.0.0
     */
    public function setOrderCondition(ShippingRuleOrderCondition|string|array|null $condition): void
    {
        if (is_string($condition)) {
            $condition = Json::decodeIfJson($condition);
        }

        if (!$condition instanceof ShippingRuleOrderCondition) {
            $condition['class'] = ShippingRuleOrderCondition::class;
            $condition = Craft::$app->getConditions()->createCondition($condition);
            /** @var ShippingRuleOrderCondition $condition */
        }
        $condition->forProjectConfig = false;

        $this->_orderCondition = $condition;
    }

    /**
     * @return ShippingRuleOrderCondition
     * @since 5.0.0
     */
    public function getOrderCondition(): ShippingRuleOrderCondition
    {
        $condition = $this->_orderCondition ?? new ShippingRuleOrderCondition();
        $condition->mainTag = 'div';
        $condition->name = 'orderCondition';
        $condition->storeId = $this->storeId;

        return $condition;
    }

    /**
     * @inheritdoc
     */
    public function matchOrder(Order $order): bool
    {
        if (!$this->enabled) {
            return false;
        }

        $lineItems = $order->getLineItems();

        if ($this->orderConditionFormula) {
            $fieldsAsArray = $order->getSerializedFieldValues();
            $orderAsArray = $order->toArray([], ['lineItems.snapshot', 'shippingAddress', 'billingAddress']);
            $orderConditionParams = [
                'order' => array_merge($orderAsArray, $fieldsAsArray),
            ];
            if (!Plugin::getInstance()->getFormulas()->evaluateCondition($this->orderConditionFormula, $orderConditionParams, 'Evaluate Shipping Rule Order Condition Formula')) {
                return false;
            }
        }

        $nonShippableItems = [];
        foreach ($lineItems as $item) {
            if ($item->getIsShippable()) {
                continue;
            }

            $nonShippableItems[$item->id] = $item->id;
        }

        $wholeOrderNonShippable = count($nonShippableItems) > 0 && count($lineItems) == count($nonShippableItems);

        if ($wholeOrderNonShippable) {
            return false;
        }

        $shippingRuleCategories = $this->getShippingRuleCategories();
        $orderShippingCategories = $this->_getUniqueCategoryIdsInOrder($order);
        [$disallowedCategories, $requiredCategories] = $this->_getRequiredAndDisallowedCategoriesFromRule($shippingRuleCategories);

        // Does the order have any disallowed categories in the cart?
        $result = array_intersect($orderShippingCategories, $disallowedCategories);
        if (!empty($result)) {
            return false;
        }

        // Does the order have all required categories in the cart?
        $result = !array_diff($requiredCategories, $orderShippingCategories);
        if (!$result) {
            return false;
        }

        // Order condition builder match
        if (!$this->getOrderCondition()->matchElement($order)) {
            return false;
        }

        // all rules match
        return true;
    }

    /**
     * @return ShippingRuleCategory[]
     * @throws InvalidConfigException
     */
    public function getShippingRuleCategories(): array
    {
        if ($this->_shippingRuleCategories === null && $this->id) {
            $this->_shippingRuleCategories = Plugin::getInstance()->getShippingRuleCategories()->getShippingRuleCategoriesByRuleId($this->id);
        }

        return $this->_shippingRuleCategories ?? [];
    }

    /**
     * @param ShippingRuleCategory[] $models
     */
    public function setShippingRuleCategories(array $models): void
    {
        $this->_shippingRuleCategories = $models;
    }

    /**
     * @inheritdoc
     */
    public function getOptions(): array
    {
        return $this->getAttributes();
    }

    /**
     * @inheritdoc
     */
    public function getPercentageRate(?int $shippingCategoryId = null): float
    {
        return $this->_getRate('percentageRate', $shippingCategoryId);
    }

    /**
     * @inheritdoc
     */
    public function getPerItemRate(?int $shippingCategoryId = null): float
    {
        return $this->_getRate('perItemRate', $shippingCategoryId);
    }

    /**
     * @inheritdoc
     */
    public function getWeightRate(?int $shippingCategoryId = null): float
    {
        return $this->_getRate('weightRate', $shippingCategoryId);
    }

    /**
     * @inheritdoc
     */
    public function getBaseRate(): float
    {
        return (float)$this->baseRate;
    }

    /**@inheritdoc
     */
    public function getMaxRate(): float
    {
        return (float)$this->maxRate;
    }

    /**
     * @inheritdoc
     */
    public function getMinRate(): float
    {
        return (float)$this->minRate;
    }

    /**
     * @inheritdoc
     */
    public function getDescription(): string
    {
        return $this->description ?? '';
    }

    /**
     * @since 3.2.7
     */
    public function validateShippingRuleCategories(string $attribute): void
    {
        $ruleCategories = $this->$attribute;

        if (!empty($ruleCategories)) {
            foreach ($ruleCategories as $key => $ruleCategory) {
                if (!$ruleCategory->validate()) {
                    $this->addModelErrors($ruleCategory, $attribute . '.' . $key);
                }
            }
        }
    }

    /**
     * @param $attribute
     * @param int|null $shippingCategoryId
     * @return mixed
     * @throws InvalidConfigException
     */
    private function _getRate($attribute, ?int $shippingCategoryId = null): mixed
    {
        if (!$shippingCategoryId) {
            return $this->$attribute;
        }

        foreach ($this->getShippingRuleCategories() as $ruleCategory) {
            if ($shippingCategoryId === $ruleCategory->shippingCategoryId && $ruleCategory->$attribute !== null) {
                return $ruleCategory->$attribute;
            }
        }

        return $this->$attribute;
    }
}
