<?php
namespace Craft;

/**
 * Class Market_OrderTypeRecord
 *
 * @property int                         id
 * @property string                      name
 * @property string                      handle
 * @property int                         fieldLayoutId
 * @property string                      purgeIncompletedCartDuration
 *
 * @property FieldLayoutRecord           fieldLayout
 * @property Market_OrderStatusRecord[]  orderStatuses
 * @property Market_OrderStatusRecord    defaultStatus
 * @package Craft
 */
class Market_OrderTypeRecord extends BaseRecord
{
    /**
     * @return string
     */
    public function getTableName()
    {
        return 'market_ordertypes';
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
    public function defineRelations()
    {
        return [
            'fieldLayout'    => [
                static::BELONGS_TO,
                'FieldLayoutRecord',
                'onDelete' => static::SET_NULL
            ],
            'orderStatuses'  => [
                static::HAS_MANY,
                'Market_OrderStatusRecord',
                'orderTypeId'
            ],
            'defaultStatus'  => [
                static::HAS_ONE,
                'Market_OrderStatusRecord',
                'orderTypeId',
                'condition' => 'defaultStatus.default = 1'
            ],
        ];
    }

    /**
     * @return array
     */
    protected function defineAttributes()
    {
        return [
            'name'             => [AttributeType::Name, 'required' => true],
            'handle'           => [AttributeType::Handle, 'required' => true]
        ];
    }

}