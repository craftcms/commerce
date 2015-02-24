<?php

namespace Craft;

/**
 * Class Market_ShippingRuleRecord
 *
 * @property int    id
 * @property string name
 * @property string description
 * @property int    countryId
 * @property int    stateId
 * @property int    methodId
 * @property int    priority
 * @property bool   $enabled
 * @property int    minQty
 * @property int    maxQty
 * @property float  minTotal
 * @property float  maxTotal
 * @property float  minWeight
 * @property float  maxWeight
 * @property float  baseRate
 * @property float  perItemRate
 * @property float  weightRate
 * @property float  percentageRate
 * @property float  minRate
 * @property float  maxRate
 *
 * @property Market_CountryRecord $country
 * @property Market_StateRecord $state
 * @property Market_ShippingMethodRecord $method
 * @package Craft
 */
class Market_ShippingRuleRecord extends BaseRecord
{
	public function getTableName()
	{
		return 'market_shippingrules';
	}

    public function defineIndexes()
    {
        return [
            ['columns' => ['name'], 'unique' => true],
            ['columns' => ['methodId']],
        ];
    }

    public function defineRelations()
    {
        return [
            'country' => [self::BELONGS_TO, 'Market_CountryRecord'],
            'state'   => [self::BELONGS_TO, 'Market_StateRecord'],
            'method'  => [self::BELONGS_TO, 'Market_ShippingMethodRecord', 'required' => true],
        ];
    }

    protected function defineAttributes()
	{
		return [
            'name'           => [AttributeType::String, 'required' => true],
            'description'    => [AttributeType::String],
            'methodId'       => [AttributeType::Number, 'required' => true],
            'priority'       => [AttributeType::Number, 'required' => true, 'default' => 0],
            'enabled'        => [AttributeType::Bool, 'required' => true, 'default' => 1],
            //filters
            'minQty'         => [AttributeType::Number, 'required' => true, 'default' => 0],
            'maxQty'         => [AttributeType::Number, 'required' => true, 'default' => 0],
            'minTotal'       => [AttributeType::Number, 'required' => true, 'default' => 0, 'decimals' => 5],
            'maxTotal'       => [AttributeType::Number, 'required' => true, 'default' => 0, 'decimals' => 5],
            'minWeight'      => [AttributeType::Number, 'required' => true, 'default' => 0, 'decimals' => 5],
            'maxWeight'      => [AttributeType::Number, 'required' => true, 'default' => 0, 'decimals' => 5],
            //charges
            'baseRate'       => [AttributeType::Number, 'required' => true, 'default' => 0, 'decimals' => 5],
            'perItemRate'    => [AttributeType::Number, 'required' => true, 'default' => 0, 'decimals' => 5],
            'weightRate'     => [AttributeType::Number, 'required' => true, 'default' => 0, 'decimals' => 5],
            'percentageRate' => [AttributeType::Number, 'required' => true, 'default' => 0, 'decimals' => 5],
            'minRate'        => [AttributeType::Number, 'required' => true, 'default' => 0, 'decimals' => 5],
            'maxRate'        => [AttributeType::Number, 'required' => true, 'default' => 0, 'decimals' => 5],
		];
	}
}