<?php
namespace Craft;

use Commerce\Traits\Commerce_ModelRelationsTrait;

/**
 * Shipping rule model
 *
 * @property int $id
 * @property string $name
 * @property string $description
 * @property int $countryId
 * @property int $stateId
 * @property int $methodId
 * @property int $priority
 * @property bool $enabled
 * @property int $minQty
 * @property int $maxQty
 * @property float $minTotal
 * @property float $maxTotal
 * @property float $minWeight
 * @property float $maxWeight
 * @property float $baseRate
 * @property float $perItemRate
 * @property float $weightRate
 * @property float $percentageRate
 * @property float $minRate
 * @property float $maxRate
 *
 * @property Commerce_CountryRecord $country
 * @property Commerce_StateRecord $state
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
    use Commerce_ModelRelationsTrait;

    /**
     * Hard coded rule handle
     *
     * @return string
     */
    public function getHandle()
    {
        return 'commerceRuleId' . $this->id;
    }

    /**
     * @return bool
     */
    public function getIsEnabled()
    {
        return $this->enabled;
    }

    /**
     * @param Commerce_OrderModel $order
     * @return bool
     */
    public function matchOrder(Commerce_OrderModel $order)
    {
        if (!$this->enabled) {
            return false;
        }

        $floatFields = ['minTotal', 'maxTotal', 'minWeight', 'maxWeight'];
        foreach ($floatFields as $field) {
            $this->$field *= 1;
        }

        if ($this->countryId && !$order->shippingAddressId) {
            return false;
        }

        if ($this->stateId && !$order->shippingAddressId) {
            return false;
        }

        // country geographical filters
        if ($this->countryId && $this->countryId != $order->shippingAddress->countryId) {
            return false;
        }

        // state filters
        if ($this->stateId && $this->state->name != $order->shippingAddress->getStateText()) {
            return false;
        }

        // order qty rules are inclusive (min <= x <= max)
        if ($this->minQty AND $this->minQty > $order->totalQty) {
            return false;
        }
        if ($this->maxQty AND $this->maxQty < $order->totalQty) {
            return false;
        }

        // order total rules exclude maximum limit (min <= x < max)
        if ($this->minTotal AND $this->minTotal > $order->itemTotal) {
            return false;
        }
        if ($this->maxTotal AND $this->maxTotal <= $order->itemTotal) {
            return false;
        }

        // order weight rules exclude maximum limit (min <= x < max)
        if ($this->minWeight AND $this->minWeight > $order->totalWeight) {
            return false;
        }
        if ($this->maxWeight AND $this->maxWeight <= $order->totalWeight) {
            return false;
        }

        // all rules match
        return true;
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
    public function getPercentageRate()
    {
        return $this->getAttribute('percentageRate');
    }

    /**
     * @return float
     */
    public function getPerItemRate()
    {
        return $this->getAttribute('perItemRate');
    }

    /**
     * @return float
     */
    public function getWeightRate()
    {
        return $this->getAttribute('weightRate');
    }

    /**
     * @return float
     */
    public function getBaseRate()
    {
        return $this->getAttribute('baseRate');
    }

    /**
     * @return float
     */
    public function getMaxRate()
    {
        return $this->getAttribute('maxRate');
    }

    /**
     * @return float
     */
    public function getMinRate()
    {
        return $this->getAttribute('minRate');
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        if($this->getAttribute('description')){
            return $this->getAttribute('description');
        }

        $description = '';
        if ($this->perItemRate || $this->percentageRate) {
            if ($this->perItemRate) {
                $description .= $this->perItemRate * 1 . '$ ';
            }

            if ($this->percentageRate) {
                if ($this->perItemRate) {
                    $description .= Craft::t('and') . ' ';
                }

                $description .= $this->percentageRate * 100 . '% ';
            }

            $description .= Craft::t('per item') . ' ';
        }

        if ($this->baseRate) {
            if ($description) {
                $description .= Craft::t('and') . ' ';
            }
            $description .= $this->baseRate * 1 . '$ ' . Craft::t('base rate');
        }

        return $description;
    }

    /**
     * @return array
     */
    protected function defineAttributes()
    {
        return [
            'id' => [AttributeType::Number],
            'name' => [AttributeType::String, 'required' => true],
            'description' => [AttributeType::String],
            'countryId' => [AttributeType::Number],
            'stateId' => [AttributeType::Number],
            'methodId' => [AttributeType::Number, 'required' => true],
            'priority' => [
                AttributeType::Number,
                'required' => true,
                'default' => 0
            ],
            'enabled' => [
                AttributeType::Bool,
                'required' => true,
                'default' => 1
            ],
            //filters
            'minQty' => [
                AttributeType::Number,
                'required' => true,
                'default' => 0
            ],
            'maxQty' => [
                AttributeType::Number,
                'required' => true,
                'default' => 0
            ],
            'minTotal' => [
                AttributeType::Number,
                'required' => true,
                'default' => 0,
                'decimals' => 5
            ],
            'maxTotal' => [
                AttributeType::Number,
                'required' => true,
                'default' => 0,
                'decimals' => 5
            ],
            'minWeight' => [
                AttributeType::Number,
                'required' => true,
                'default' => 0,
                'decimals' => 5
            ],
            'maxWeight' => [
                AttributeType::Number,
                'required' => true,
                'default' => 0,
                'decimals' => 5
            ],
            //charges
            'baseRate' => [
                AttributeType::Number,
                'required' => true,
                'default' => 0,
                'decimals' => 5
            ],
            'perItemRate' => [
                AttributeType::Number,
                'required' => true,
                'default' => 0,
                'decimals' => 5
            ],
            'weightRate' => [
                AttributeType::Number,
                'required' => true,
                'default' => 0,
                'decimals' => 5
            ],
            'percentageRate' => [
                AttributeType::Number,
                'required' => true,
                'default' => 0,
                'decimals' => 5
            ],
            'minRate' => [
                AttributeType::Number,
                'required' => true,
                'default' => 0,
                'decimals' => 5
            ],
            'maxRate' => [
                AttributeType::Number,
                'required' => true,
                'default' => 0,
                'decimals' => 5
            ],
        ];
    }
}
