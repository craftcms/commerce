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
	/**
	 * Hard coded rule handle
	 *
	 * @return string
	 */
	public function getHandle()
	{
		return 'commerceRuleId'.$this->id;
	}

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

		$floatFields = ['minTotal', 'maxTotal', 'minWeight', 'maxWeight'];
		foreach ($floatFields as $field)
		{
			$this->$field *= 1;
		}

		if ($this->shippingZoneId && !$order->shippingAddressId)
		{
			return false;
		}

		$shippingZone = $this->getShippingZone();

		if ($shippingZone)
		{
			if ($shippingZone->countryBased)
			{
				$countryIds = $shippingZone->getCountryIds();

				if (!in_array($$order->getShippingAddress()->countryId, $countryIds))
				{
					return false;
				}
			}
			else
			{
				foreach ($shippingZone->states as $state)
				{
					if ($state->getCountry()->id != $order->getShippingAddress()->countryId || strcasecmp($state->name, $order->getShippingAddress()->getStateText()) != 0)
					{
						return false;
					}
				}
			}
		}

		// order qty rules are inclusive (min <= x <= max)
		if ($this->minQty AND $this->minQty > $order->totalQty)
		{
			return false;
		}
		if ($this->maxQty AND $this->maxQty < $order->totalQty)
		{
			return false;
		}

		// order total rules exclude maximum limit (min <= x < max)
		if ($this->minTotal AND $this->minTotal > $order->itemTotal)
		{
			return false;
		}
		if ($this->maxTotal AND $this->maxTotal <= $order->itemTotal)
		{
			return false;
		}

		// order weight rules exclude maximum limit (min <= x < max)
		if ($this->minWeight AND $this->minWeight > $order->totalWeight)
		{
			return false;
		}
		if ($this->maxWeight AND $this->maxWeight <= $order->totalWeight)
		{
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
