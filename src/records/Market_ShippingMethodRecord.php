<?php

namespace Craft;

/**
 * Class Market_ShippingMethodRecord
 *
 * @property int                         $id
 * @property string                      $name
 * @property bool                        $enabled
 * @property bool                        $default
 *
 * @property Market_ShippingRuleRecord[] rules
 * @package Craft
 */
class Market_ShippingMethodRecord extends BaseRecord
{
    public function getTableName()
    {
        return 'market_shippingmethods';
    }

    public function defineIndexes()
    {
        return [
            ['columns' => ['name'], 'unique' => true],
        ];
    }

    public function defineRelations()
    {
        return [
            'rules' => [
                self::HAS_MANY,
                'Market_ShippingRuleRecord',
                'methodId',
                'order' => 'rules.priority'
            ],
        ];
    }

    protected function defineAttributes()
    {
        return [
            'name'    => [AttributeType::String, 'required' => true],
            'enabled' => [
                AttributeType::Bool,
                'required' => true,
                'default'  => 1
            ],
            'default' => [
                AttributeType::Bool,
                'required' => true,
                'default'  => 0
            ],
        ];
    }
}