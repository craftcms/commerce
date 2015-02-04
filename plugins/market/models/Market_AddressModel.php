<?php

namespace Craft;

/**
 * Class Market_AddressModel
 *
 * @property int    $id
 * @property string $firstName
 * @property string lastName
 * @property string address1
 * @property string address2
 * @property string zipCode
 * @property string phone
 * @property string alternativePhone
 * @property string company
 * @property string stateName
 * @property int    countryId
 * @property int    stateId
 * @package Craft
 */
class Market_AddressModel extends BaseModel
{
	/** @var Market_CountryModel */
	public $country;
	/** @var Market_StateModel */
	public $state;
	/** @var int|string Either ID of a state or name of state if it's not present in our DB */
	public $stateValue;

//    public function getCpEditUrl()
//    {
//        return UrlHelper::getCpUrl('market/settings/addresses/' . $this->id);
//    }

	public function getStateText()
	{
		return $this->stateName ?: $this->state->name;
	}

	protected function defineAttributes()
	{
		return array(
			'firstName'        => AttributeType::String,
			'lastName'         => AttributeType::String,
			'address1'         => AttributeType::String,
			'address2'         => AttributeType::String,
			'zipCode'          => AttributeType::String,
			'phone'            => AttributeType::String,
			'alternativePhone' => AttributeType::String,
			'company'          => AttributeType::String,
			'stateName'        => AttributeType::String,
			'countryId'        => AttributeType::Number,
			'stateId'          => AttributeType::Number,
		);
	}

	/**
	 * @param array|Market_AddressRecord $values
	 *
	 * @return $this
	 */
	public static function populateModel($values)
	{
		$model = parent::populateModel($values);
		if (is_object($values) && $values instanceof Market_AddressRecord) {
			$model->country = $values->country;
			$model->state   = $values->state;
		}

		return $model;
	}
}