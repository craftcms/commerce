<?php

namespace Craft;

use Market\Traits\Market_ModelRelationsTrait;

/**
 * Class Market_AddressModel
 *
 * @property int                    $id
 * @property string                 $firstName
 * @property string                 lastName
 * @property string                 address1
 * @property string                 address2
 * @property string                 zipCode
 * @property string                 phone
 * @property string                 alternativePhone
 * @property string                 company
 * @property string                 stateName
 * @property int                    countryId
 * @property int                    stateId
 *
 * @property Market_CountryModel    $country
 * @property Market_StateModel      $state
 * @package Craft
 */
class Market_AddressModel extends BaseModel
{
	use Market_ModelRelationsTrait;

	/** @var int|string Either ID of a state or name of state if it's not present in the DB */
	public $stateValue;

	public function getStateText()
	{
		return $this->stateName ?: $this->state->name;
	}

	protected function defineAttributes()
	{
		return array(
			'id'               => AttributeType::Number,
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
}