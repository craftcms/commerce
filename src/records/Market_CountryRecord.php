<?php

namespace Craft;

/**
 * Class Market_CountryRecord
 *
 * @property int    $id
 * @property string $name
 * @property string $iso
 * @property bool   $stateRequired
 * @package Craft
 */
class Market_CountryRecord extends BaseRecord
{

	public function getTableName()
	{
		return 'market_countries';
	}

	public function defineIndexes()
	{
		return [
			['columns' => ['name'], 'unique' => true],
			['columns' => ['iso'], 'unique' => true],
		];
	}

	protected function defineAttributes()
	{
		return [
			'name'          => [AttributeType::String, 'required' => true],
			'iso'           => [AttributeType::String, 'required' => true, 'maxLength' => 2],
			'stateRequired' => [AttributeType::Bool, 'required' => true, 'default' => 0],
		];
	}
}