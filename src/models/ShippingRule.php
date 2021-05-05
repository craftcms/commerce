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

/**
 * Shipping rule model
 *
 * @property bool $isEnabled whether this shipping rule enabled for listing and selection
 * @property array $options
 * @property array|ShippingRuleCategory[] $shippingRuleCategories
 * @property mixed $shippingZone
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class ShippingRule extends Model implements ShippingRuleInterface
{
    /**
     * @var int ID
     */
    public $id;

    /**
     * @var string Name
     */
    public $name;

    /**
     * @var string Description
     */
    public $description;

    /**
     * @var int Shipping zone ID
     */
    public $shippingZoneId;

    /**
     * @var int Shipping method ID
     */
    public $methodId;

    /**
     * @var int Priority
     */
    public $priority = 0;

    /**
     * @var bool Enabled
     */
    public $enabled = true;

    /**
     * @var string Order Condition Formula
     */
    public $orderConditionFormula = '';

    /**
     * @var int Minimum Quantity
     */
    public $minQty = 0;

    /**
     * @var int Maximum Quantity
     */
    public $maxQty = 0;

    /**
     * @var float Minimum total
     */
    public $minTotal = 0;

    /**
     * @var float Maximum total
     */
    public $maxTotal = 0;

    /**
     * @var float Minimum type rule
     */
    public $minMaxTotalType = 'salePrice';

    /**
     * @var float Minimum Weight
     */
    public $minWeight = 0;

    /**
     * @var float Maximum Weight
     */
    public $maxWeight = 0;

    /**
     * @var float Base rate
     */
    public $baseRate = 0;

    /**
     * @var float Per item rate
     */
    public $perItemRate = 0;

    /**
     * @var float Percentage rate
     */
    public $percentageRate = 0;

    /**
     * @var float Weight rate
     */
    public $weightRate = 0;

    /**
     * @var float Minimum Rate
     */
    public $minRate = 0;

    /**
     * @var float Maximum rate
     */
    public $maxRate = 0;

    /**
     * @var bool Is lite shipping rule
     */
    public $isLite = 0;

    /**
     * @param Order $order
     * @return array
     */
    private function _getUniqueCategoryIdsInOrder(Order $order): array
    {
        $orderShippingCategories = [];
        foreach ($order->lineItems as $lineItem) {
            // Dont' look at the shipping category of non shippable products.
            if ($lineItem->getPurchasable() && $lineItem->getPurchasable()->getIsShippable()) {
                $orderShippingCategories[] = $lineItem->shippingCategoryId;
            }
        }
        $orderShippingCategories = array_unique($orderShippingCategories);
        return $orderShippingCategories;
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
     * @var ShippingCategory[]
     */
    private $_shippingRuleCategories;


    /**
     * @inheritdoc
     */
    public function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [
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
            ], 'required'
        ];

        $rules[] = [
            [
                'perItemRate',
                'weightRate',
                'percentageRate',
            ], 'number'
        ];

        $rules[] = [['shippingRuleCategories'], 'validateShippingRuleCategories', 'skipOnEmpty' => true];

        $rules[] = [['orderConditionFormula'], 'string', 'length' => [1, 65000], 'skipOnEmpty' => true];
        $rules[] = [
            'orderConditionFormula', function($attribute, $params, $validator) {
                if($this->{$attribute}) {
                    $order = Order::find()->one();
                    if (!$order) {
                        $order = new Order();
                    }
                    $orderConditionParams = [
                        'order' => $order->toArray([], ['lineItems.snapshot', 'shippingAddress', 'billingAddress'])
                    ];
                    if (!Plugin::getInstance()->getFormulas()->validateConditionSyntax($this->{$attribute}, $orderConditionParams)) {
                        $this->addError($attribute, Craft::t('commerce', 'Invalid order condition syntax.'));
                    }
                }
            }
        ];
        
        return $rules;
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
                'order' => array_merge($orderAsArray, $fieldsAsArray)
            ];
            if (!Plugin::getInstance()->getFormulas()->evaluateCondition($this->orderConditionFormula, $orderConditionParams, 'Evaluate Shipping Rule Order Condition Formula')) {
                return false;
            }
        }

        $nonShippableItems = [];
        foreach ($lineItems as $item) {
            $purchasable = $item->getPurchasable();
            if ($purchasable && !$purchasable->getIsShippable()) {
                $nonShippableItems[$item->id] = $item->id;
            }
        }

        $wholeOrderNonShippable = $nonShippableItems > 0 && count($lineItems) == count($nonShippableItems);

        if ($wholeOrderNonShippable) {
            return false;
        }

        $shippingRuleCategories = $this->getShippingRuleCategories();
        $orderShippingCategories = $this->_getUniqueCategoryIdsInOrder($order);
        list($disallowedCategories, $requiredCategories) = $this->_getRequiredAndDisallowedCategoriesFromRule($shippingRuleCategories);

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

        $this->getShippingRuleCategories();
        $floatFields = ['minTotal', 'maxTotal', 'minWeight', 'maxWeight'];
        foreach ($floatFields as $field) {
            $this->$field *= 1;
        }

        $shippingZone = $this->getShippingZone();
        $shippingAddress = $order->getShippingAddress() ?? $order->getEstimatedShippingAddress();

        if ($shippingZone && !$shippingAddress) {
            return false;
        }

        /** @var ShippingAddressZone $shippingZone */
        if ($shippingZone && !Plugin::getInstance()->getAddresses()->addressWithinZone($shippingAddress, $shippingZone)) {
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
     */
    public function getShippingRuleCategories(): array
    {
        if (null === $this->_shippingRuleCategories) {
            $this->_shippingRuleCategories = Plugin::getInstance()->getShippingRuleCategories()->getShippingRuleCategoriesByRuleId((int)$this->id);
        }

        return $this->_shippingRuleCategories;
    }

    /**
     * @param ShippingRuleCategory[] $models
     */
    public function setShippingRuleCategories(array $models)
    {
        $this->_shippingRuleCategories = $models;
    }

    /**
     * @return mixed
     */
    public function getShippingZone()
    {
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
    public function getPercentageRate($shippingCategoryId = null): float
    {
        return $this->_getRate('percentageRate', $shippingCategoryId);
    }

    /**
     * @inheritdoc
     */
    public function getPerItemRate($shippingCategoryId = null): float
    {
        return $this->_getRate('perItemRate', $shippingCategoryId);
    }

    /**
     * @inheritdoc
     */
    public function getWeightRate($shippingCategoryId = null): float
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
        return $this->description;
    }

    /**
     * @param $attribute
     * @since 3.2.7
     */
    public function validateShippingRuleCategories($attribute)
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
     * @param $shippingCategoryId
     * @return mixed
     */
    private function _getRate($attribute, $shippingCategoryId = null)
    {
        if (!$shippingCategoryId) {
            return $this->$attribute;
        }

        foreach ($this->getShippingRuleCategories() as $ruleCategory) {
            if ((int)$shippingCategoryId === (int)$ruleCategory->shippingCategoryId && $ruleCategory->$attribute !== null) {
                return $ruleCategory->$attribute;
            }
        }

        return $this->$attribute;
    }
}
