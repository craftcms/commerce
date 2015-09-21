<?php

namespace Craft;

/**
 * Class Commerce_AddressRecord
 *
 * @property int                   $id
 * @property string                $firstName
 * @property string                lastName
 * @property string                address1
 * @property string                address2
 * @property string                city
 * @property string                zipCode
 * @property string                phone
 * @property string                alternativePhone
 * @property string                company
 * @property string                stateName
 * @property int                   countryId
 * @property int                   stateId
 * @property int                   customerId
 *
 * @property Commerce_CountryRecord  country
 * @property Commerce_StateRecord    state
 * @property Commerce_CustomerRecord customer
 *
 * @package Craft
 */
class Commerce_AddressRecord extends BaseRecord
{
	/**
	 * @return string
	 */
	public function getTableName ()
	{
		return 'commerce_addresses';
	}

	/**
	 * @return array
	 */
	public function defineRelations ()
	{
		return [
			'country'  => [
				static::BELONGS_TO,
				'Commerce_CountryRecord',
				'onDelete' => self::RESTRICT,
				'onUpdate' => self::CASCADE,
				'required' => true
			],
			'state'    => [
				static::BELONGS_TO,
				'Commerce_StateRecord',
				'onDelete' => self::RESTRICT,
				'onUpdate' => self::CASCADE
			],
			'customer' => [
				static::BELONGS_TO,
				'Commerce_CustomerRecord',
				'onDelete' => self::CASCADE,
				'onUpdate' => self::CASCADE,
				'required' => true
			],
		];
	}

	/**
	 * @return array
	 */
	protected function defineAttributes ()
	{
		return [
			'firstName'        => [AttributeType::String, 'required' => true],
			'lastName'         => [AttributeType::String, 'required' => true],
			'countryId'        => [AttributeType::Number, 'required' => true],
			'address1'         => AttributeType::String,
			'address2'         => AttributeType::String,
			'city'             => AttributeType::String,
			'zipCode'          => AttributeType::String,
			'phone'            => AttributeType::String,
			'alternativePhone' => AttributeType::String,
			'company'          => AttributeType::String,
			'stateName'        => AttributeType::String,
			'customerId'       => [AttributeType::Number, 'required' => true],
		];
	}
}