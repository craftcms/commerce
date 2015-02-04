<?php

namespace Craft;

/**
 * Class Stripey_StateRecord
 *
 * @property int                   $id
 * @property string                $name
 * @property string                $abbreviation
 * @property int                   $countryId
 *
 * @property Stripey_CountryRecord $country
 * @package Craft
 */
class Stripey_StateRecord extends BaseRecord
{

	public function getTableName()
	{
		return 'stripey_states';
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
			'country' => array(static::BELONGS_TO, 'Stripey_CountryRecord', 'onDelete' => self::CASCADE, 'onUpdate' => self::CASCADE, 'required' => true),
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