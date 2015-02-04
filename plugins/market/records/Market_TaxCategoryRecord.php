<?php

namespace Craft;

/**
 * Class Market_TaxCategoryRecord
 *
 * @property int    $id
 * @property string $name
 * @property string $code
 * @property string $description
 * @property bool   $default
 * @package Craft
 */
class Market_TaxCategoryRecord extends BaseRecord
{

	public function getTableName()
	{
		return 'market_taxcategories';
	}

	protected function defineAttributes()
	{
		return array(
			'name'        => array(AttributeType::String, 'required' => true),
			'code'        => AttributeType::String,
			'description' => AttributeType::String,
			'default'     => array(AttributeType::Bool, 'default' => 0, 'required' => true),
		);
	}

}