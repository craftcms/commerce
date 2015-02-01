<?php

namespace Craft;

/**
 * Class Stripey_AddressRecord
 *
 * @property int                   $id
 * @property string                $firstName
 * @property string                lastName
 * @property string                address1
 * @property string                address2
 * @property string                zipCode
 * @property string                phone
 * @property string                alternativePhone
 * @property string                company
 * @property string                stateName
 * @property int                   countryId
 * @property int                   stateId
 *
 * @property Stripey_CountryRecord $country
 * @property Stripey_StateRecord   $state
 * @package Craft
 */
class Stripey_AddressRecord extends BaseRecord
{

    public function getTableName()
    {
        return 'stripey_addresses';
    }

    public function defineRelations()
    {
        return array(
            'country' => array(static::BELONGS_TO, 'Stripey_CountryRecord', 'onDelete' => self::CASCADE, 'onUpdate' => self::CASCADE, 'required' => true),
            'state'   => array(static::BELONGS_TO, 'Stripey_StateRecord', 'onDelete' => self::CASCADE, 'onUpdate' => self::CASCADE),
        );
    }

    protected function defineAttributes()
    {
        return array(
            'firstName'        => array(AttributeType::String, 'required' => true),
            'lastName'         => array(AttributeType::String, 'required' => true),
            'address1'         => AttributeType::String,
            'address2'         => AttributeType::String,
            'zipCode'          => AttributeType::String,
            'phone'            => AttributeType::String,
            'alternativePhone' => AttributeType::String,
            'company'          => AttributeType::String,
            'stateName'        => AttributeType::String,
        );
    }
}