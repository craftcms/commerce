<?php

namespace craft\commerce\models;

use craft\commerce\base\Model;
use craft\commerce\base\ShippingRuleInterface;
use craft\commerce\Plugin;
use craft\commerce\records\ShippingRuleCategory;

/**
 * Shipping rule model
 *
 * @property \craft\commerce\models\ShippingMethod $method
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.models
 * @since     1.0
 */
class ShippingRule extends Model implements ShippingRuleInterface
{

    // Properties
    // =========================================================================

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
     * @var \craft\commerce\models\ShippingCategory[]
     */
    private $_shippingRuleCategories;

    public function rules()
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
            ]
        ];
    }

    /**
     * @return bool
     */
    public function getIsEnabled()
    {
        return $this->enabled;
    }

    /**
     * @param \craft\commerce\elements\Order $order
     *
     * @return bool
     */
    public function matchOrder(\craft\commerce\elements\Order $order)
    {
        if (!$this->enabled) {
            return false;
        }

        $shippingRuleCategories = $this->getShippingRuleCategories();

        $orderShippingCategories = [];
        foreach ($order->lineItems as $lineItem) {
            $orderShippingCategories[] = $lineItem->shippingCategoryId;
        }
        $orderShippingCategories = array_unique($orderShippingCategories);

        $disallowedCategories = [];
        $allowedCategories = [];
        $requiredCategories = [];
        foreach ($shippingRuleCategories as $ruleCategory) {
            if ($ruleCategory->condition == ShippingRuleCategory::CONDITION_DISALLOW) {
                $disallowedCategories[] = $ruleCategory->shippingCategoryId;
            }

            if ($ruleCategory->condition == ShippingRuleCategory::CONDITION_ALLOW) {
                $allowedCategories[] = $ruleCategory->shippingCategoryId;
            }

            if ($ruleCategory->condition == ShippingRuleCategory::CONDITION_REQUIRE) {
                $requiredCategories[] = $ruleCategory->shippingCategoryId;
            }
        }

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
        $shippingAddress = $order->getShippingAddress();

        if ($shippingZone && !$shippingAddress) {
            return false;
        }

        if ($shippingZone) {
            if ($shippingZone->countryBased) {
                $countryIds = $shippingZone->getCountryIds();

                if (!in_array($shippingAddress->countryId, $countryIds)) {
                    return false;
                }
            } else {
                $states = [];
                $countries = [];
                foreach ($shippingZone->states as $state) {
                    $states[] = $state->id;
                    $countries[] = $state->countryId;
                }

                $countryAndStateMatch = (bool)(in_array($shippingAddress->countryId, $countries) && in_array($shippingAddress->stateId, $states));
                $countryAndStateNameMatch = (bool)(in_array($shippingAddress->countryId, $countries) && strcasecmp($state->name, $shippingAddress->getStateText()) == 0);
                $countryAndStateAbbrMatch = (bool)(in_array($shippingAddress->countryId, $countries) && strcasecmp($state->abbreviation, $shippingAddress->getStateText()) == 0);

                if (!($countryAndStateMatch || $countryAndStateNameMatch || $countryAndStateAbbrMatch)) {
                    return false;
                }
            }
        }

        // order qty rules are inclusive (min <= x <= max)
        if ($this->minQty && $this->minQty > $order->totalQty) {
            return false;
        }
        if ($this->maxQty && $this->maxQty < $order->totalQty) {
            return false;
        }

        // order total rules exclude maximum limit (min <= x < max)
        if ($this->minTotal && $this->minTotal > $order->itemTotal) {
            return false;
        }
        if ($this->maxTotal && $this->maxTotal <= $order->itemTotal) {
            return false;
        }

        // order weight rules exclude maximum limit (min <= x < max)
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
    public function getShippingRuleCategories()
    {
        if (null === $this->_shippingRuleCategories) {
            $this->_shippingRuleCategories = Plugin::getInstance()->getShippingRuleCategories()->getShippingRuleCategoriesByRuleId($this->id);
        }

        return $this->_shippingRuleCategories;
    }

    /**
     * @param \craft\commerce\models\ShippingRuleCategory[] $models
     *
     * @return array
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
     * @return array
     */
    public function getOptions()
    {
        return $this->getAttributes();
    }

    /**
     * @return float
     */
    public function getPercentageRate($shippingCategoryId = null)
    {
        return $this->_getRate('percentageRate', $shippingCategoryId);
    }

    /**
     * @param      $attribute
     * @param null $shippingCategoryId
     *
     * @return mixed
     */
    private function _getRate($attribute, $shippingCategoryId = null)
    {
        if (!$shippingCategoryId) {
            return $this->$attribute;
        }

        foreach ($this->getShippingRuleCategories() as $ruleCategory) {
            if ($shippingCategoryId == $ruleCategory->shippingCategoryId && $ruleCategory->$attribute !== null) {
                return $ruleCategory->$attribute;
            }
        }

        return $this->$attribute;
    }

    /**
     * @return float
     */
    public function getPerItemRate($shippingCategoryId = null)
    {
        return $this->_getRate('perItemRate', $shippingCategoryId);
    }

    /**
     * @return float
     */
    public function getWeightRate($shippingCategoryId = null)
    {
        return $this->_getRate('weightRate', $shippingCategoryId);
    }

    /**
     * @return float
     */
    public function getBaseRate()
    {
        return (float) $this->baseRate;
    }

    /**
     * @return float
     */
    public function getMaxRate()
    {
        return (float) $this->maxRate;
    }

    /**
     * @return float
     */
    public function getMinRate()
    {
        return (float) $this->minRate;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }
}
