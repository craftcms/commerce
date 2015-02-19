<?php

namespace Craft;
use Market\Traits\Market_ModelRelationsTrait;

/**
 * Class Market_TaxRateModel
 *
 * @property int    $id
 * @property string $name
 * @property float  $rate
 * @property bool   $include
 * @property bool   $showInLabel
 * @property int    $taxZoneId
 * @property int    $taxCategoryId
 *
 * @property Market_TaxZoneModel    $taxZone
 * @property Market_TaxCategoryModel    $taxCategory
 * @package Craft
 */
class Market_TaxRateModel extends BaseModel
{
    use Market_ModelRelationsTrait;

	public function getCpEditUrl()
	{
		return UrlHelper::getCpUrl('market/settings/taxrates/' . $this->id);
	}

	protected function defineAttributes()
	{
		return [
			'id'            => AttributeType::Number,
			'name'          => AttributeType::String,
			'rate'          => array(AttributeType::Number, 'default' => .05),
			'include'       => AttributeType::Bool,
			'showInLabel'   => AttributeType::Bool,

			'taxCategoryId' => AttributeType::Number,
			'taxZoneId'     => AttributeType::Number,
		];
	}
}