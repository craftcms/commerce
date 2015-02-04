<?php

namespace Craft;

/**
 * Class Market_StateModel
 *
 * @property int    $id
 * @property string $name
 * @property string $abbreviation
 * @property int    $countryId
 * @property string $countryName
 * @package Craft
 */
class Market_StateModel extends BaseModel
{
	public $countryName;

	public function getCpEditUrl()
	{
		return UrlHelper::getCpUrl('market/settings/states/' . $this->id);
	}

	public function formatName()
	{
		return $this->name . ' (' . $this->countryName . ')';
	}

	protected function defineAttributes()
	{
		return array(
			'id'           => AttributeType::Number,
			'name'         => AttributeType::String,
			'abbreviation' => AttributeType::String,
			'countryId'    => AttributeType::Number,
		);
	}

	public static function populateModel($values)
	{
		$model = parent::populateModel($values);
		if (is_object($values)) {
			$model->countryName = $values->country->name;
		}

		return $model;
	}
}