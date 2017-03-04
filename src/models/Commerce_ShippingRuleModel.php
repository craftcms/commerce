<?php
namespace Craft;

/**
 * Shipping rule model
 *
 * @property int                           $id
 * @property string                        $name
 * @property string                        $description
 * @property int                           shippingZoneId
 * @property int                           $methodId
 * @property int                           $priority
 * @property bool                          $enabled
 * @property int                           $minQty
 * @property int                           $maxQty
 * @property float                         $minTotal
 * @property float                         $maxTotal
 * @property float                         $minWeight
 * @property float                         $maxWeight
 * @property float                         $baseRate
 * @property float                         $perItemRate
 * @property float                         $weightRate
 * @property float                         $percentageRate
 * @property float                         $minRate
 * @property float                         $maxRate
 *
 * @property Commerce_ShippingMethodRecord $method
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.models
 * @since     1.0
 */
class Commerce_ShippingRuleModel extends BaseModel implements \Commerce\Interfaces\ShippingRule
{

    private $_shippingRuleCategories;

    /**
     * @return bool
     */
    public function getIsEnabled()
    {
        return $this->enabled;
    }

    /**
     * @deprecated
     * @return Commerce_CountryModel|null
     */
    public function getCountry()
    {

        craft()->deprecator->log('Commerce_ShippingRuleModel::getCountry():removed', 'You should no longer try to get a single country (`$rule->getCountry()` or `$rule->country`) from a shipping rule, since shipping zones are now used which support multiple countries');

        $zone = $this->getShippingZone();

        if ($zone && $zone->countryBased)
        {
            $countries = craft()->commerce_shippingZones->getCountriesByShippingZoneId($zone->id);

            if (!empty($countries))
            {
                return ArrayHelper::getFirstValue($countries);
            }
        }

        return null;
    }

    /**
     * @deprecated
     * @return Commerce_StateModel|null
     */
    public function getState()
    {
        craft()->deprecator->log('Commerce_ShippingRuleModel::getState():removed', 'You should no longer try to get a single state (`$rule->getState()` or `$rule->state`) from a shipping rule, since shipping zones are now used which support multiple states.');

        $zone = $this->getShippingZone();

        if ($zone && !$zone->countryBased)
        {
            $states = craft()->commerce_shippingZones->getStatesByShippingZoneId($zone->id);

            if (!empty($states))
            {
                return ArrayHelper::getFirstValue($states);
            }
        }

        return null;
    }

    public function getShippingZone()
    {
        return craft()->commerce_shippingZones->getShippingZoneById($this->shippingZoneId);
    }

    /**
     * @param Commerce_OrderModel $order
     *
     * @return bool
     */
    public function matchOrder(Commerce_OrderModel $order)
    {
        if (!$this->enabled)
        {
            return false;
        }

        $shippingRuleCategories = $this->getShippingRuleCategories();

        $orderShippingCategories = [];
        foreach ($order->lineItems as $lineItem)
        {
            $orderShippingCategories[] = $lineItem->shippingCategoryId;
        }
        $orderShippingCategories = array_unique($orderShippingCategories);

        $disallowedCategories = [];
        $allowedCategories = [];
        $requiredCategories = [];
        foreach ($shippingRuleCategories as $ruleCategory)
        {
            if ($ruleCategory->condition == Commerce_ShippingRuleCategoryRecord::CONDITION_DISALLOW)
            {
                $disallowedCategories[] = $ruleCategory->shippingCategoryId;
            }

            if ($ruleCategory->condition == Commerce_ShippingRuleCategoryRecord::CONDITION_ALLOW)
            {
                $allowedCategories[] = $ruleCategory->shippingCategoryId;
            }

            if ($ruleCategory->condition == Commerce_ShippingRuleCategoryRecord::CONDITION_REQUIRE)
            {
                $requiredCategories[] = $ruleCategory->shippingCategoryId;
            }
        }

        // Does the order have any disallowed categories in the cart?
        $result = array_intersect($orderShippingCategories, $disallowedCategories);
        if (!empty($result))
        {
            return false;
        }

        // Does the order have all required categories in the cart?
        $result = !array_diff($requiredCategories, $orderShippingCategories);
        if (!$result)
        {
            return false;
        }

        $this->getShippingRuleCategories();
        $floatFields = ['minTotal', 'maxTotal', 'minWeight', 'maxWeight'];
        foreach ($floatFields as $field)
        {
            $this->$field *= 1;
        }

        $shippingZone = $this->getShippingZone();
        $shippingAddress = $order->getShippingAddress();

        if ($shippingZone && !$shippingAddress)
        {
            return false;
        }

        if ($shippingZone)
        {
            if ($shippingZone->countryBased)
            {
                $countryIds = $shippingZone->getCountryIds();

                if (!in_array($shippingAddress->countryId, $countryIds))
                {
                    return false;
                }
            }
            else
            {
                $states = [];
                $countries = [];
                foreach ($shippingZone->states as $state)
                {
                    $states[] = $state->id;
                    $countries[] = $state->countryId;
                }

                $countryAndStateMatch = (bool) (in_array($shippingAddress->countryId, $countries) && in_array($shippingAddress->stateId, $states));
                $countryAndStateNameMatch = (bool) (in_array($shippingAddress->countryId, $countries) && strcasecmp($state->name, $shippingAddress->getStateText()) == 0);
                $countryAndStateAbbrMatch = (bool) (in_array($shippingAddress->countryId, $countries) && strcasecmp($state->abbreviation, $shippingAddress->getStateText()) == 0);

                if (!($countryAndStateMatch || $countryAndStateNameMatch || $countryAndStateAbbrMatch))
                {
                    return false;
                }
            }
        }

        // order qty rules are inclusive (min <= x <= max)
        if ($this->minQty && $this->minQty > $order->totalQty)
        {
            return false;
        }
        if ($this->maxQty && $this->maxQty < $order->totalQty)
        {
            return false;
        }

        // order total rules exclude maximum limit (min <= x < max)
        if ($this->minTotal && $this->minTotal > $order->itemTotal)
        {
            return false;
        }
        if ($this->maxTotal && $this->maxTotal <= $order->itemTotal)
        {
            return false;
        }

        // order weight rules exclude maximum limit (min <= x < max)
        if ($this->minWeight && $this->minWeight > $order->totalWeight)
        {
            return false;
        }
        if ($this->maxWeight && $this->maxWeight <= $order->totalWeight)
        {
            return false;
        }

        // all rules match
        return true;
    }

    /**
     * @return Commerce_ShippingRuleCategoryModel[]
     */
    public function getShippingRuleCategories()
    {
        if(!isset($this->_shippingRuleCategories))
        {
            $this->_shippingRuleCategories = craft()->commerce_shippingRules->getShippingRuleCategoryByRuleId($this->id);
        }

        return $this->_shippingRuleCategories;
    }

    /**
     * @param Commerce_ShippingRuleCategoryModel[] $models
     *
     * @return array
     */
    public function setShippingRuleCategories(array $models)
    {
        $this->_shippingRuleCategories = $models;
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

    private function _getRate($attribute, $shippingCategoryId = null)
    {
        if (!$shippingCategoryId)
        {
            return $this->getAttribute($attribute);
        }

        foreach ($this->getShippingRuleCategories() as $ruleCategory)
        {
            if ($shippingCategoryId == $ruleCategory->shippingCategoryId && $ruleCategory->$attribute !== null)
            {
                return $ruleCategory->$attribute;
            }
        }

        return $this->getAttribute($attribute);
    }

    /**
     * @return float
     */
    public function getBaseRate()
    {
        return (float) $this->getAttribute('baseRate');
    }

    /**
     * @return float
     */
    public function getMaxRate()
    {
        return (float) $this->getAttribute('maxRate');
    }

    /**
     * @return float
     */
    public function getMinRate()
    {
        return (float) $this->getAttribute('minRate');
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->getAttribute('description');
    }

    /**
     * @return array
     */
    protected function defineAttributes()
    {
        return [
            'id'             => [AttributeType::Number],
            'name'           => [AttributeType::String, 'required' => true],
            'description'    => [AttributeType::String],
            'shippingZoneId' => [AttributeType::Number, 'default' => null],
            'methodId'       => [AttributeType::Number, 'required' => true],
            'priority'       => [
                AttributeType::Number,
                'required' => true,
                'default'  => 0
            ],
            'enabled'        => [
                AttributeType::Bool,
                'required' => true,
                'default'  => true
            ],
            //filters
            'minQty'         => [
                AttributeType::Number,
                'required' => true,
                'default'  => 0
            ],
            'maxQty'         => [
                AttributeType::Number,
                'required' => true,
                'default'  => 0
            ],
            'minTotal'       => [
                AttributeType::Number,
                'required' => true,
                'default'  => 0,
                'decimals' => 4
            ],
            'maxTotal'       => [
                AttributeType::Number,
                'required' => true,
                'default'  => 0,
                'decimals' => 4
            ],
            'minWeight'      => [
                AttributeType::Number,
                'required' => true,
                'default'  => 0,
                'decimals' => 4
            ],
            'maxWeight'      => [
                AttributeType::Number,
                'required' => true,
                'default'  => 0,
                'decimals' => 4
            ],
            //charges
            'baseRate'       => [
                AttributeType::Number,
                'required' => true,
                'default'  => 0,
                'decimals' => 4
            ],
            'perItemRate'    => [
                AttributeType::Number,
                'required' => true,
                'default'  => 0,
                'decimals' => 4
            ],
            'weightRate'     => [
                AttributeType::Number,
                'required' => true,
                'default'  => 0,
                'decimals' => 4
            ],
            'percentageRate' => [
                AttributeType::Number,
                'required' => true,
                'default'  => 0,
                'decimals' => 4
            ],
            'minRate'        => [
                AttributeType::Number,
                'required' => true,
                'default'  => 0,
                'decimals' => 4
            ],
            'maxRate'        => [
                AttributeType::Number,
                'required' => true,
                'default'  => 0,
                'decimals' => 4
            ],
        ];
    }
}
