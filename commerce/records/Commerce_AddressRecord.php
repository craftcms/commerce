<?php
namespace Craft;

/**
 * Address record.
 *
 * @property int $id
 * @property string $attention
 * @property string $title
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
 * @property string $businessId
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
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class Commerce_AddressRecord extends BaseRecord
{

    /** @var int|string Either ID of a state or name of state if it's not present in the DB */
    public $stateValue;

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
                'onDelete' => self::SET_NULL,
            ],
            'state' => [
                static::BELONGS_TO,
                'Commerce_StateRecord',
                'onDelete' => self::SET_NULL
            ]
        ];
    }

    /**
     * @return array
     */
    protected function defineAttributes()
    {
        return [
            'attention'        => AttributeType::String,
            'title'            => AttributeType::String,
            'firstName'        => [
                AttributeType::String,
                'required' => true,
                'label' => 'First Name',
            ],
            'lastName'         => [
                AttributeType::String,
                'required' => true,
                'label' => 'Last Name',
            ],
            'countryId'        => AttributeType::Number,
            'stateId'          => AttributeType::Number,
            'address1'         => AttributeType::String,
            'address2'         => AttributeType::String,
            'city'             => AttributeType::String,
            'zipCode'          => AttributeType::String,
            'phone'            => AttributeType::String,
            'alternativePhone' => AttributeType::String,
            'businessName'     => AttributeType::String,
            'businessTaxId'    => AttributeType::String,
            'businessId'       => AttributeType::String,
            'stateName'        => AttributeType::String,
        ];
    }
}
