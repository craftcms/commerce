<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use Craft;
use craft\commerce\base\AdjusterInterface;
use craft\commerce\base\Model;
use craft\commerce\base\ShippingRuleInterface;
use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use craft\commerce\records\ShippingRule as ShippingRuleRecord;
use craft\commerce\records\ShippingRuleCategory as ShippingRuleCategoryRecord;
use craft\errors\DeprecationException;
use DateTime;
use yii\base\InvalidConfigException;

/**
 * Shipping rule model
 *
 * @property bool $isEnabled whether this shipping rule enabled for listing and selection
 * @property array $options
 * @property array|ShippingRuleCategory[] $shippingRuleCategories
 * @property mixed $shippingZone
 * @property bool $isLite
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class ShippingRule extends Model implements ShippingRuleInterface
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
     * @var int|null Shipping zone ID
     */
    public ?int $shippingZoneId = null;

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
     * @var int Minimum Quantity
     */
    public int $minQty = 0;

    /**
     * @var int Maximum Quantity
     */
    public int $maxQty = 0;

    /**
     * @var float Minimum total
     */
    public float $minTotal = 0;

    /**
     * @var float Maximum total
     */
    public float $maxTotal = 0;

    /**
     * @var string Minimum type rule
     */
    public string $minMaxTotalType = ShippingRuleRecord::TYPE_MIN_MAX_TOTAL_SALEPRICE;

    /**
     * @var float Minimum Weight
     */
    public float $minWeight = 0;

    /**
     * @var float Maximum Weight
     */
    public float $maxWeight = 0;

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
     * @throws InvalidConfigException
     */
    private function _getUniqueCategoryIdsInOrder(Order $order): array
    {
        $orderShippingCategories = [];
        foreach ($order->getLineItems() as $lineItem) {
            // Don't look at the shipping category of non-shippable products.
            if ($lineItem->getPurchasable() && Plugin::getInstance()->getPurchasables()->isPurchasableShippable($lineItem->getPurchasable(), $order)) {
                $orderShippingCategories[] = $lineItem->shippingCategoryId;
            }
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
                    'minQty',
                    'maxQty',
                    'minTotal',
                    'minMaxTotalType',
                    'maxTotal',
                    'minWeight',
                    'maxWeight',
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
        ];
    }

    /**
     * @inheritdoc
     */
    public function extraFields(): array
    {
        $fields = parent::extraFields();
        $fields[] = 'shippingRuleCategories';
        $fields[] = 'shippingZone';

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
            $purchasable = $item->getPurchasable();
            if ($purchasable && !Plugin::getInstance()->getPurchasables()->isPurchasableShippable($purchasable, $order)) {
                $nonShippableItems[$item->id] = $item->id;
            }
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

        $floatFields = ['minTotal', 'maxTotal', 'minWeight', 'maxWeight'];
        foreach ($floatFields as $field) {
            $this->$field *= 1;
        }

        $shippingZone = $this->getShippingZone();
        $shippingAddress = $order->getShippingAddress() ?? $order->getEstimatedShippingAddress();

        if ($shippingZone && !$shippingAddress) {
            return false;
        }

        if ($shippingZone && !$shippingZone->getCondition()->matchElement($shippingAddress)) {
            return false;
        }

        // order qty rules are inclusive (min > x <  max)
        if ($this->minQty && $this->minQty > $order->totalQty) {
            return false;
        }
        if ($this->maxQty && $this->maxQty < $order->totalQty) {
            return false;
        }

        $itemSubtotal = $order->getItemSubtotal();

        switch ($this->minMaxTotalType) {
            case ShippingRuleRecord::TYPE_MIN_MAX_TOTAL_SALEPRICE:

                $itemTotal = $itemSubtotal;
                break;
            case ShippingRuleRecord::TYPE_MIN_MAX_TOTAL_SALEPRICE_WITH_DISCOUNTS:

                $discountAdjustments = [];
                $discountAdjusters = Plugin::getInstance()->getOrderAdjustments()->getDiscountAdjusters();
                foreach ($discountAdjusters as $discountAdjuster) {
                    /** @var AdjusterInterface $discountAdjuster */
                    $adjuster = new $discountAdjuster();
                    $discountAdjustments = array_merge($discountAdjustments, $adjuster->adjust($order));
                }

                $discountAmount = 0;
                foreach ($discountAdjustments as $adjustment) {
                    $discountAmount += $adjustment->amount;
                }

                $itemTotal = $itemSubtotal + $discountAmount;
                break;
            default:

                $itemTotal = $itemSubtotal; // Default is ShippingRule::TYPE_MIN_ORDER_TOTAL_SALEPRICE
                break;
        }

        // order total rules exclude maximum limit (min > x <= max)
        if ($this->minTotal && $this->minTotal > $itemTotal) {
            return false;
        }

        if ($this->maxTotal && $this->maxTotal <= $itemTotal) {
            return false;
        }

        // order weight rules exclude maximum limit (min > x <= max)
        if ($this->minWeight && $this->minWeight > $order->totalWeight) {
            return false;
        }
        if ($this->maxWeight && $this->maxWeight <= $order->totalWeight) {
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
     * @throws InvalidConfigException
     */
    public function getShippingZone(): ?ShippingAddressZone
    {
        if ($this->shippingZoneId === null) {
            return null;
        }

        return Plugin::getInstance()->getShippingZones()->getShippingZoneById($this->shippingZoneId);
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

    /**
     * @return bool
     * @throws DeprecationException
     * @since 4.5.0
     * @deprecated in 4.5.0.
     */
    public function getIsLite(): bool
    {
        Craft::$app->getDeprecator()->log(__METHOD__, 'ShippingRule::getIsLite() is deprecated.');
        return false;
    }

    /**
     * @param bool $isLite
     * @return void
     * @throws DeprecationException
     * @since 4.5.0
     * @deprecated in 4.5.0.
     */
    public function setIsLite(bool $isLite): void
    {
        Craft::$app->getDeprecator()->log(__METHOD__, 'ShippingRule::setIsLite() is deprecated.');
    }
}
