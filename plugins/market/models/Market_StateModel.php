<?php

namespace Craft;
use Market\Traits\Market_ModelRelationsTrait;

/**
 * Class Market_StateModel
 *
 * @property int    $id
 * @property string $name
 * @property string $abbreviation
 * @property int    $countryId
 *
 * @property Market_CountryRecord $country
 * @package Craft
 */
class Market_StateModel extends BaseModel
{
    use Market_ModelRelationsTrait;

	public function getCpEditUrl()
	{
		return UrlHelper::getCpUrl('market/settings/states/' . $this->id);
	}

    /**
     * @return string
     */
	public function formatName()
	{
		return $this->name . ' (' . $this->country->name . ')';
	}

	protected function defineAttributes()
	{
		return [
			'id'           => AttributeType::Number,
			'name'         => AttributeType::String,
			'abbreviation' => AttributeType::String,
			'countryId'    => AttributeType::Number,
		];
	}
}