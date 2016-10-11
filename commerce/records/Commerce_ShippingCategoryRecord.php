<?php
namespace Craft;

/**
 * Tax category record.
 *
 * @property int $id
 * @property string $name
 * @property string $handle
 * @property string $description
 * @property bool $default
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.2
 */
class Commerce_ShippingCategoryRecord extends BaseRecord
{
    /**
     * @return string
     */
    public function getTableName()
    {
        return 'commerce_shippingcategories';
    }

    /**
     * @return array
     */
    public function defineIndexes()
    {
        return [
            ['columns' => ['handle'], 'unique' => true],
        ];
    }

    /**
     * @return array
     */
    protected function defineAttributes()
    {
        return [
            'name' => [AttributeType::String, 'required' => true],
            'handle' => [AttributeType::String, 'required' => true],
            'description' => AttributeType::String,
            'default' => [
                AttributeType::Bool,
                'default' => 0,
                'required' => true
            ],
        ];
    }

}