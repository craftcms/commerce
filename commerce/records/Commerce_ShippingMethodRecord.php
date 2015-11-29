<?php
namespace Craft;

/**
 * Shipping method record.
 *
 * @property int $id
 * @property string $name
 * @property string $handle
 * @property bool $enabled
 *
 * @property Commerce_ShippingRuleRecord[] $rules
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class Commerce_ShippingMethodRecord extends BaseRecord
{
    /**
     * @return string
     */
    public function getTableName()
    {
        return 'commerce_shippingmethods';
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
    public function scopes()
    {
        return [
            'enabled' => [
                'condition' => 'enabled = 1',
            ],
        ];
    }

    /**
     * @return array
     */
    public function defineRelations()
    {
        return [
            'rules' => [
                self::HAS_MANY,
                'Commerce_ShippingRuleRecord',
                'methodId',
                'order' => 'rules.priority'
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
            'handle' => [AttributeType::Handle, 'required' => true],
            'enabled' => [
                AttributeType::Bool,
                'required' => true,
                'default' => 1
            ]
        ];
    }
}
