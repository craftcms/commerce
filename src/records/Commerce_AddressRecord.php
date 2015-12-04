<?php
namespace Craft;

/**
 * Address record.
 *
 * @property int $id
 * @property string $firstName
 * @property string $lastName
 * @property string $address1
 * @property string $address2
 * @property string $city
 * @property string $zipCode
 * @property string $phone
 * @property string $alternativePhone
 * @property string $businessName
 * @property string $businessTaxId
 * @property string $stateName
 * @property int $countryId
 * @property int $stateId
 * @property int $customerId
 *
 * @property Commerce_CountryRecord $country
 * @property Commerce_StateRecord $state
 * @property Commerce_CustomerRecord $customer
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class Commerce_AddressRecord extends BaseRecord
{
    /**
     * @return string
     */
    public function getTableName()
    {
        return 'commerce_addresses';
    }

    /**
     * @return array
     */
    public function defineRelations()
    {
        return [
            'country' => [
                static::BELONGS_TO,
                'Commerce_CountryRecord',
                'onDelete' => self::RESTRICT,
                'onUpdate' => self::CASCADE,
                'required' => true
            ],
            'state' => [
                static::BELONGS_TO,
                'Commerce_StateRecord',
                'onDelete' => self::RESTRICT,
                'onUpdate' => self::CASCADE
            ]
        ];
    }

    /**
     * @return array
     */
    protected function defineAttributes()
    {
        return [
            'firstName' => [AttributeType::String, 'required' => true],
            'lastName' => [AttributeType::String, 'required' => true],
            'countryId' => [AttributeType::Number, 'required' => true],
            'stateId' => AttributeType::Number,
            'address1' => AttributeType::String,
            'address2' => AttributeType::String,
            'city' => AttributeType::String,
            'zipCode' => AttributeType::String,
            'phone' => AttributeType::String,
            'alternativePhone' => AttributeType::String,
            'businessName' => AttributeType::String,
            'businessTaxId' => AttributeType::String,
            'stateName' => AttributeType::String,
        ];
    }
}
