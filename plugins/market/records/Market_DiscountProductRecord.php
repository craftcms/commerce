<?php

namespace Craft;

/**
 * Class Market_DiscountProductRecord
 *
 * @property int id
 * @property int discountId
 * @property int productId
 * @package Craft
 */
class Market_DiscountProductRecord extends BaseRecord
{
    public function getTableName()
    {
        return 'market_discount_products';
    }

    /**
     * @return array
     */
    public function defineIndexes()
    {
        return [
            ['columns' => ['discountId', 'productId'], 'unique' => true],
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
            'product'  => [
                static::BELONGS_TO,
                'Market_ProductRecord',
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
            'productId'  => [AttributeType::Number, 'required' => true],
        ];
    }

}