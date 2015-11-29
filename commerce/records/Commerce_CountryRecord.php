<?php
namespace Craft;

/**
 * Country record.
 *
 * @property int $id
 * @property string $name
 * @property string $iso
 * @property bool $stateRequired
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class Commerce_CountryRecord extends BaseRecord
{

    /**
     * @return string
     */
    public function getTableName()
    {
        return 'commerce_countries';
    }

    /**
     * @return array
     */
    public function defineIndexes()
    {
        return [
            ['columns' => ['name'], 'unique' => true],
            ['columns' => ['iso'], 'unique' => true],
        ];
    }

    /**
     * @return array
     */
    protected function defineAttributes()
    {
        return [
            'name' => [AttributeType::String, 'required' => true],
            'iso' => [
                AttributeType::String,
                'required' => true,
                'maxLength' => 2
            ],
            'stateRequired' => [
                AttributeType::Bool,
                'required' => true,
                'default' => 0
            ],
        ];
    }
}