<?php
namespace Craft;

/**
 * Shipping zone record.
 *
 * @property int                      $id
 * @property string                   $name
 * @property string                   $description
 * @property bool                     $countryBased
 * @property bool                     $default
 *
 * @property Commerce_CountryRecord[] $countries
 * @property Commerce_StateRecord[]   $states
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class Commerce_ShippingZoneRecord extends BaseRecord
{

    /**
     * @return string
     */
    public function getTableName()
    {
        return 'commerce_shippingzones';
    }

    /**
     * @return array
     */
    public function defineIndexes()
    {
        return [
            ['columns' => ['name'], 'unique' => true],
        ];
    }

    /**
     * @return array
     */
    public function defineRelations()
    {
        return [
            'countries' => [
                static::MANY_MANY,
                'Commerce_CountryRecord',
                'commerce_shippingzone_countries(countryId, shippingZoneId)'
            ],
            'states'    => [
                static::MANY_MANY,
                'Commerce_StateRecord',
                'commerce_shippingzone_states(stateId, shippingZoneId)'
            ],
        ];
    }

    /**
     * @return array
     */
    protected function defineAttributes()
    {
        return [
            'name'         => [AttributeType::String, 'required' => true],
            'description'  => AttributeType::String,
            'countryBased' => [
                AttributeType::Bool,
                'required' => true,
                'default'  => 1
            ]
        ];
    }
}