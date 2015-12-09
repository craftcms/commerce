<?php
namespace Craft;

/**
 * Taz zone country
 *
 * @property int $taxZoneId
 * @property int $countryId
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class Commerce_TaxZoneCountryRecord extends BaseRecord
{

    /**
     * @return string
     */
    public function getTableName()
    {
        return "commerce_taxzone_countries";
    }

    /**
     * @return array
     */
    public function defineIndexes()
    {
        return [
            ['columns' => ['taxZoneId']],
            ['columns' => ['countryId']],
            ['columns' => ['taxZoneId', 'countryId'], 'unique' => true],
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
            'taxZone' => [
                static::BELONGS_TO,
                'Commerce_TaxZoneRecord',
                'onDelete' => self::CASCADE,
                'onUpdate' => self::CASCADE,
                'required' => true
            ],
            'country' => [
                static::BELONGS_TO,
                'Commerce_CountryRecord',
                'onDelete' => self::CASCADE,
                'onUpdate' => self::CASCADE,
                'required' => true
            ],
        ];
    }

}