<?php

namespace Craft;

/**
 * Class Commerce_TaxCategoryRecord
 *
 * @property int    $id
 * @property string $name
 * @property string $handle
 * @property string $description
 * @property bool   $default
 * @package Craft
 */
class Commerce_TaxCategoryRecord extends BaseRecord
{
	/**
	 * @return string
	 */
	public function getTableName ()
	{
		return 'commerce_taxcategories';
	}

	/**
	 * @return array
	 */
	public function defineIndexes ()
	{
		return [
			['columns' => ['handle'], 'unique' => true],
		];
	}

	/**
	 * @return array
	 */
	protected function defineAttributes ()
	{
		return [
			'name'        => [AttributeType::String, 'required' => true],
			'handle'      => [AttributeType::String, 'required' => true],
			'description' => AttributeType::String,
			'default'     => [
				AttributeType::Bool,
				'default'  => 0,
				'required' => true
			],
		];
	}

}