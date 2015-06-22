<?php

namespace Craft;

/**
 * Class Market_OrderStatusRecord
 *
 * @property int                    id
 * @property string                 name
 * @property int                    orderTypeId
 * @property string                 handle
 * @property string                 color
 * @property bool                   default
 *
 * @property Market_OrderTypeRecord orderType
 * @property Market_EmailRecord[]   emails
 * @package Craft
 */
class Market_OrderStatusRecord extends BaseRecord
{

    public function getTableName()
    {
        return 'market_orderstatuses';
    }

    /**
     * @return array
     */
    public function defineRelations()
    {
        return [
            'orderType' => [
                static::BELONGS_TO,
                'Market_OrderTypeRecord',
                'required' => true
            ],
            'emails'    => [
                static::MANY_MANY,
                'Market_EmailRecord',
                'market_orderstatus_emails(orderStatusId, emailId)'
            ],
        ];
    }

    protected function defineAttributes()
    {
        return [
            'name'        => [AttributeType::String, 'required' => true],
            'orderTypeId' => [AttributeType::Number, 'required' => true],
            'handle'      => [AttributeType::Handle, 'required' => true],
            'color'       => [
                AttributeType::String,
                'column'   => ColumnType::Char,
                'length'   => 7,
                'required' => true
            ],
            'default'     => [
                AttributeType::Bool,
                'default'  => 0,
                'required' => true
            ],
        ];
    }
}