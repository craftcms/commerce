<?php

namespace Craft;

/**
 * Class Market_StateRecord
 *
 * @property int                  $id
 * @property string               $name
 * @property string               $abbreviation
 * @property int                  $countryId
 *
 * @property Market_CountryRecord $country
 * @package Craft
 */
class Market_StateRecord extends BaseRecord
{

	/**
	 * @return string
	 */
	public function getTableName ()
	{
		return 'market_states';
	}

	/**
	 * @return array
	 */
	public function defineIndexes ()
	{
		return [
			['columns' => ['name', 'countryId'], 'unique' => true],
		];
	}

	/**
	 * @return array
	 */
	public function defineRelations ()
	{
		return [
			'country' => [
				static::BELONGS_TO,
				'Market_CountryRecord',
				'onDelete' => self::CASCADE,
				'onUpdate' => self::CASCADE,
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
			'name'         => [AttributeType::String, 'required' => true],
			'abbreviation' => AttributeType::String,
			'countryId'    => [AttributeType::Number, 'required' => true],
		];
	}
}