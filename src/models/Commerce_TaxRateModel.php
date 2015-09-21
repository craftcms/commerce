<?php

namespace Craft;

use Commerce\Traits\Commerce_ModelRelationsTrait;

/**
 * Class Commerce_TaxRateModel
 *
 * @property int                     $id
 * @property string                  $name
 * @property float                   $rate
 * @property bool                    $include
 * @property bool                    $showInLabel
 * @property int                     $taxZoneId
 * @property int                     $taxCategoryId
 *
 * @property Commerce_TaxZoneModel     $taxZone
 * @property Commerce_TaxCategoryModel $taxCategory
 * @package Craft
 */
class Commerce_TaxRateModel extends BaseModel
{
	use Commerce_ModelRelationsTrait;

	/**
	 * @return string
	 */
	public function getCpEditUrl ()
	{
		return UrlHelper::getCpUrl('commerce/settings/taxrates/'.$this->id);
	}

	/**
	 * @return array
	 */
	protected function defineAttributes ()
	{
		return [
			'id'            => AttributeType::Number,
			'name'          => AttributeType::String,
			'rate'          => [AttributeType::Number, 'default' => .05, 'decimals' => 5],
			'include'       => AttributeType::Bool,
			'showInLabel'   => AttributeType::Bool,
			'taxCategoryId' => [AttributeType::Number, 'required' => true],
			'taxZoneId'     => [AttributeType::Number, 'required' => true]
		];
	}
}