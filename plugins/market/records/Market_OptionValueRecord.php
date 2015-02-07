<?php

namespace Craft;

/**
 * Class Market_OptionValueRecord
 *
 * @property int                      id
 * @property string                   name
 * @property string                   displayName
 * @property int                      optionTypeId
 * @property int                      position
 *
 * @property Market_OptionTypeRecord optionType
 * @package Craft
 */
class Market_OptionValueRecord extends BaseRecord
{

	public function getTableName()
	{
		return 'market_optionvalues';
	}

	public function defaultScope()
	{
		return array(
			'order' => 'position',
		);
	}

	public function defineRelations()
	{
		return array(
//            'productOptionTypes' => array(static::HAS_MANY,'Market_ProductOptionTypes','optionTypeId'),
//            'product' => array(static::HAS_MANY,array('user_id'=>'id'),'through'=>'roles'),
			'optionType' => array(static::BELONGS_TO, 'Market_OptionTypeRecord', 'required' => true),
		);
	}

	public function defineIndexes()
	{
		return array(
//            array('columns' => array('typeId')),
//            array('columns' => array('availableOn')),
//            array('columns' => array('expiresOn')),
		);
	}

	protected function defineAttributes()
	{
		return array(
			'name'        => AttributeType::String,
			'displayName' => AttributeType::String,
			'position'    => AttributeType::Number,
		);
	}

}