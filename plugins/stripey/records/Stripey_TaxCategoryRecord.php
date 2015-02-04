<?php

namespace Craft;

/**
 * Class Stripey_TaxCategoryRecord
 *
 * @property int    $id
 * @property string $name
 * @property string $code
 * @property string $description
 * @property bool   $default
 * @package Craft
 */
class Stripey_TaxCategoryRecord extends BaseRecord
{

	public function getTableName()
	{
		return 'stripey_taxcategories';
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

	protected function afterSave()
	{
		//only one default category is allowed
		if ($this->default) {
			self::updateAll(array('default' => 0), 'id != ?', array($this->id));
		}
		parent::afterSave();
	}

}