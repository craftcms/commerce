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
 * @property Market_CountryRecord $country
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
		return array(
			array('columns' => array('name', 'countryId'), 'unique' => true),
		);
	}

	public function defineRelations()
	{
		return array(
			'country' => array(static::BELONGS_TO, 'Market_CountryRecord', 'onDelete' => self::CASCADE, 'onUpdate' => self::CASCADE, 'required' => true),
		);
	}

	protected function defineAttributes()
	{
		return array(
			'name'         => array(AttributeType::String, 'required' => true),
			'abbreviation' => AttributeType::String,
		);
	}
}