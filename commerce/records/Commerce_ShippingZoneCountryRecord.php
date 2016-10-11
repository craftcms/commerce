<?php
namespace Craft;

/**
 * Taz zone country
 *
 * @property int $shippingZoneId
 * @property int $countryId
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class Commerce_ShippingZoneCountryRecord extends BaseRecord
{

    /**
     * @return string
     */
    public function getTableName()
    {
        return "commerce_shippingzone_countries";
    }

    /**
     * @return array
     */
    public function defineIndexes()
    {
        return [
            ['columns' => ['shippingZoneId']],
            ['columns' => ['countryId']],
            ['columns' => ['shippingZoneId', 'countryId'], 'unique' => true],
        ];
    }


    /**
     * @inheritDoc BaseRecord::defineRelations()
     *
     * @return array
     */
    public function defineRelations()
    {
        return [
            'shippingZone' => [
                static::BELONGS_TO,
                'Commerce_ShippingZoneRecord',
                'onDelete' => self::CASCADE,
                'onUpdate' => self::CASCADE,
                'required' => true
            ],
            'country'      => [
                static::BELONGS_TO,
                'Commerce_CountryRecord',
                'onDelete' => self::CASCADE,
                'onUpdate' => self::CASCADE,
                'required' => true
            ],
        ];
    }

}