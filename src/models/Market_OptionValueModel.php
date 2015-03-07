<?php

namespace Craft;

/**
 * Class Market_OptionValueModel
 *
 * @property int                    id
 * @property string                 name
 * @property string                 displayName
 * @property int                    position
 * @property int                    optionTypeId
 *
 * @property Market_OptionTypeModel optionType
 * @package Craft
 */
class Market_OptionValueModel extends BaseModel
{
	/** @var Market_OptionValueRecord */
	private $record;

	/** Required for Market Editable Table
	 * Useful to also lookup editable table order to attribute mapping
	 */
	public static function editableColumns()
	{
		return [
			[
				'attribute' => 'name',
				'heading'   => 'Name',
				'type'      => 'singleline',
				'width'     => '50%'
			], [
				'attribute' => 'displayName',
				'heading'   => 'Display Name',
				'type'      => 'singleline',
				'width'     => '50%'
			],
		];
	}

	/**
	 * @param array|Market_OptionValueRecord $values
	 *
	 * @return Market_OptionValueModel
	 */
	public static function populateModel($values)
	{
		/** @var Market_OptionValueModel $model */
		$model = parent::populateModel($values);
		if (is_object($values) && $values instanceof Market_OptionValueRecord) {
			$model->record = $values;
		}

		return $model;
	}

	function __toString()
	{
		return Craft::t($this->displayName);
	}

	public function getCpEditUrl()
	{
		return UrlHelper::getCpUrl('market/settings/optiontypes/' . $this->optionTypeId);
	}

	/**
	 * @return Market_OptionTypeModel
	 */
	public function getOptionType()
	{
		if ($this->record) {
			return Market_OptionTypeModel::populateModel($this->record->optionType);
		} else {
			return craft()->market_optionType->getById($this->optionTypeId);
		}
	}

	protected function defineAttributes()
	{
		return [
			'id'           => AttributeType::Number,
			'name'         => AttributeType::String,
			'displayName'  => AttributeType::String,
			'position'     => AttributeType::Number,
			'optionTypeId' => AttributeType::Number
		];
	}
}