<?php
namespace Craft;

/**
 * State record.
 *
 * @property int $id
 * @property string $name
 * @property string $abbreviation
 * @property int $countryId
 *
 * @property Commerce_CountryRecord $country
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class Commerce_StateRecord extends BaseRecord
{

    /**
     * @return string
     */
    public function getTableName()
    {
        return 'commerce_states';
    }

    /**
     * @return array
     */
    public function defineIndexes()
    {
        return [
            ['columns' => ['name', 'countryId'], 'unique' => true],
        ];
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
                'onDelete' => self::CASCADE,
                'onUpdate' => self::CASCADE,
                'required' => true
            ],
        ];
    }

    /**
     * @return array
     */
    protected function defineAttributes()
    {
        return [
            'name' => [AttributeType::String, 'required' => true],
            'abbreviation' => AttributeType::String,
            'countryId' => [AttributeType::Number, 'required' => true],
        ];
    }
}