<?php

namespace Craft;

/**
 * Class Market_StateRecord
 *
 * @property int                   $id
 * @property string                $name
 * @property string                $abbreviation
 * @property int                   $countryId
 *
 * @property Market_CountryRecord  $country
 * @package Craft
 */
class Market_StateRecord extends BaseRecord
{

	public function getTableName()
	{
		return 'market_states';
	}

	public function defineIndexes()
	{
		return [
			['columns' => ['name', 'countryId'], 'unique' => true],
		];
	}

	public function defineRelations()
	{
		return [
			'country' => [static::BELONGS_TO, 'Market_CountryRecord', 'onDelete' => self::CASCADE, 'onUpdate' => self::CASCADE, 'required' => true],
		];
	}

	protected function defineAttributes()
	{
		return [
			'name'         => [AttributeType::String, 'required' => true],
			'abbreviation' => AttributeType::String,
			'countryId'    => [AttributeType::Number, 'required' => true],
		];
	}
}