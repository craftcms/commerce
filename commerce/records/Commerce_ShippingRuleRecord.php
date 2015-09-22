<?php
namespace Craft;

/**
 * Shipping rule record.
 *
 * @property int                           $id
 * @property string                        $name
 * @property string                        $description
 * @property int                           $countryId
 * @property int                           $stateId
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
 * @property Commerce_CountryRecord        $country
 * @property Commerce_StateRecord          $state
 * @property Commerce_ShippingMethodRecord $method
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class Commerce_ShippingRuleRecord extends BaseRecord
{
	/**
	 * @return string
	 */
	public function getTableName ()
	{
		return 'commerce_shippingrules';
	}

	/**
	 * @return array
	 */
	public function defineIndexes ()
	{
		return [
			['columns' => ['name'], 'unique' => true],
			['columns' => ['methodId']],
		];
	}

	/**
	 * @return array
	 */
	public function defineRelations ()
	{
		return [
			'country' => [self::BELONGS_TO, 'Commerce_CountryRecord'],
			'state'   => [self::BELONGS_TO, 'Commerce_StateRecord'],
			'method'  => [
				self::BELONGS_TO,
				'Commerce_ShippingMethodRecord',
				'required' => true
			],
		];
	}

	/**
	 * @return array
	 */
	protected function defineAttributes ()
	{
		return [
			'name'           => [AttributeType::String, 'required' => true],
			'description'    => [AttributeType::String],
			'methodId'       => [AttributeType::Number, 'required' => true],
			'priority'       => [
				AttributeType::Number,
				'required' => true,
				'default'  => 0
			],
			'enabled'        => [
				AttributeType::Bool,
				'required' => true,
				'default'  => 1
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
				'decimals' => 5
			],
			'maxTotal'       => [
				AttributeType::Number,
				'required' => true,
				'default'  => 0,
				'decimals' => 5
			],
			'minWeight'      => [
				AttributeType::Number,
				'required' => true,
				'default'  => 0,
				'decimals' => 5
			],
			'maxWeight'      => [
				AttributeType::Number,
				'required' => true,
				'default'  => 0,
				'decimals' => 5
			],
			//charges
			'baseRate'       => [
				AttributeType::Number,
				'required' => true,
				'default'  => 0,
				'decimals' => 5
			],
			'perItemRate'    => [
				AttributeType::Number,
				'required' => true,
				'default'  => 0,
				'decimals' => 5
			],
			'weightRate'     => [
				AttributeType::Number,
				'required' => true,
				'default'  => 0,
				'decimals' => 5
			],
			'percentageRate' => [
				AttributeType::Number,
				'required' => true,
				'default'  => 0,
				'decimals' => 5
			],
			'minRate'        => [
				AttributeType::Number,
				'required' => true,
				'default'  => 0,
				'decimals' => 5
			],
			'maxRate'        => [
				AttributeType::Number,
				'required' => true,
				'default'  => 0,
				'decimals' => 5
			],
		];
	}
}