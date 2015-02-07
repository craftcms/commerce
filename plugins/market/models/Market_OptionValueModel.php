<?php

namespace Craft;

/**
 * Class Market_OptionValueModel
 *
 * @property int    id
 * @property string name
 * @property string displayName
 * @property int    position
 * @property int    optionTypeId
 * @package Craft
 */
class Market_OptionValueModel extends BaseModel
{
	/** Required for Market Editable Table
	 * Useful to also lookup editable table order to attribute mapping
	 */
	public static function editableColumns()
	{
		return array(
			array('attribute' => 'name',
				  'heading'   => 'Name',
				  'type'      => 'singleline',
				  'width'     => '50%'
			),
			array('attribute' => 'displayName',
				  'heading'   => 'Display Name',
				  'type'      => 'singleline',
				  'width'     => '50%'
			),
		);
	}

	function __toString()
	{
		return Craft::t($this->displayName);
	}

	public function getCpEditUrl()
	{
		return UrlHelper::getCpUrl('market/settings/optiontypes/' . $this->optionTypeId);
	}

	public function getOptionType()
	{
		return craft()->market_optionType->getById($this->optionTypeId);
	}

	protected function defineAttributes()
	{
		return array(
			'id'           => AttributeType::Number,
			'name'         => AttributeType::String,
			'displayName'  => AttributeType::String,
			'position'     => AttributeType::Number,
			'optionTypeId' => AttributeType::Number
		);
	}

}