<?php

namespace Craft;

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
 * @package Craft
 */
class Market_TaxRateModel extends BaseModel
{
	/** @var Market_TaxZoneModel */
	public $taxZone;
	/** @var Market_TaxCategoryModel */
	public $taxCategory;

	public function getCpEditUrl()
	{
		return UrlHelper::getCpUrl('market/settings/taxrates/' . $this->id);
	}

	protected function defineAttributes()
	{
		return array(
			'id'            => AttributeType::Number,
			'name'          => AttributeType::String,
			'rate'          => array(AttributeType::Number, 'default' => .05),
			'include'       => AttributeType::Bool,
			'showInLabel'   => AttributeType::Bool,

			'taxCategoryId' => AttributeType::Number,
			'taxZoneId'     => AttributeType::Number,
		);
	}

	public static function populateModel($values)
	{
		$model = parent::populateModel($values);

		if (is_object($values) && $values instanceof Market_TaxRateRecord) {
			$model->taxZone     = Market_TaxZoneModel::populateModel($values->taxZone);
			$model->taxCategory = Market_TaxCategoryModel::populateModel($values->taxCategory);
		}

		return $model;
	}

}