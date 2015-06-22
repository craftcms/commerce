<?php

namespace Craft;

/**
 * Class Market_CustomerDiscountUseRecord
 *
 * @property int                   id
 * @property int                   discountId
 * @property int                   customerId
 * @property Market_DiscountRecord discount
 * @property Market_CustomerRecord customer
 * @package Craft
 */
class Market_CustomerDiscountUseRecord extends BaseRecord
{
    public function getTableName()
    {
        return 'market_customer_discountuses';
    }

    /**
     * @return array
     */
    public function defineIndexes()
    {
        return [
            ['columns' => ['customerId', 'discountId'], 'unique' => true],
        ];
    }

    public function defineRelations()
    {
        return [
            'discount' => [
                static::BELONGS_TO,
                'Market_DiscountRecord',
                'onDelete' => self::CASCADE,
                'onUpdate' => self::CASCADE,
                'required' => true
            ],
            'customer' => [
                static::BELONGS_TO,
                'Market_CustomerRecord',
                'onDelete' => self::CASCADE,
                'onUpdate' => self::CASCADE,
                'required' => true
            ],
        ];
    }

    protected function defineAttributes()
    {
        return [
            'discountId' => [AttributeType::Number, 'required' => true],
            'customerId' => [AttributeType::Number, 'required' => true],
            'uses'       => [
                AttributeType::Number,
                'required' => true,
                'min'      => 1
            ],
        ];
    }
}