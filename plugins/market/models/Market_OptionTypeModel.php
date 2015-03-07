<?php

namespace Craft;

/**
 * Class Market_OptionTypeModel
 *
 * @property int    id
 * @property string name
 * @property string handle
 * @package Craft
 */
class Market_OptionTypeModel extends BaseModel
{
	function __toString()
	{
		return Craft::t($this->handle);
	}

	/**
	 * @return string
	 */
	public function getCpEditUrl()
	{
		return UrlHelper::getCpUrl('market/settings/optiontypes/' . $this->id);
	}

	/**
	 * [Id => name] list for dropdown
	 *
	 * @return array
	 */
	public function getSelectValues()
	{
		$values = $this->getOptionValues();

		$result = ['' => ''];
		foreach ($values as $value) {
			$result[$value->id] = $value->displayName;
		}

		return $result;
	}

	/**
	 * @return Market_OptionValueModel[]
	 */
	public function getOptionValues()
	{
		return craft()->market_optionValue->getAllByOptionTypeId($this->id);
	}

	protected function defineAttributes()
	{
		return [
			'id'     => AttributeType::Number,
			'name'   => AttributeType::String,
			'handle' => AttributeType::String
		];
	}

}