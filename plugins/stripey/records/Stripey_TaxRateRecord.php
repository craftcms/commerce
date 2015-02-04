<?php

namespace Craft;

/**
 * Class Stripey_TaxRateRecord
 *
 * @property int                       $id
 * @property string                    $name
 * @property float                     $rate
 * @property bool                      $include
 * @property bool                      $showInLabel
 * @property int                       $taxZoneId
 * @property int                       $taxCategoryId
 *
 * @property Stripey_TaxZoneRecord     $taxZone
 * @property Stripey_TaxCategoryRecord $taxCategory
 * @package Craft
 */
class Stripey_TaxRateRecord extends BaseRecord
{

	public function getTableName()
	{
		return 'stripey_taxrates';
	}

	public function defineIndexes()
	{
		return array(
			array('columns' => array('taxZoneId')),
			array('columns' => array('taxCategoryId')),
		);
	}

	public function defineRelations()
	{
		return array(
			'taxZone'     => array(static::BELONGS_TO, 'Stripey_TaxZoneRecord', 'onDelete' => self::RESTRICT, 'onUpdate' => self::CASCADE, 'required' => true),
			'taxCategory' => array(static::BELONGS_TO, 'Stripey_TaxCategoryRecord', 'onDelete' => self::RESTRICT, 'onUpdate' => self::CASCADE, 'required' => true),
		);
	}

	protected function defineAttributes()
	{
		return array(
			'name'        => array(AttributeType::String, 'required' => true),
			'rate'        => array(AttributeType::Number, 'required' => true, 'decimals' => 5),
			'include'     => array(AttributeType::Bool, 'default' => 0, 'required' => true),
			'showInLabel' => array(AttributeType::Bool, 'default' => 0, 'required' => true),
		);
	}
}