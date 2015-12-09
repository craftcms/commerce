<?php
namespace Craft;

/**
 * Discount Product type record.
 *
 * @property int $id
 * @property int $discountId
 * @property int $productTypeId
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class Commerce_DiscountProductTypeRecord extends BaseRecord
{
    /**
     * @return string
     */
    public function getTableName()
    {
        return 'commerce_discount_producttypes';
    }

    /**
     * @return array
     */
    public function defineIndexes()
    {
        return [
            ['columns' => ['discountId', 'productTypeId'], 'unique' => true],
        ];
    }

    /**
     * @return array
     */
    public function defineRelations()
    {
        return [
            'discount' => [
                static::BELONGS_TO,
                'Commerce_DiscountRecord',
                'onDelete' => self::CASCADE,
                'onUpdate' => self::CASCADE,
                'required' => true
            ],
            'productType' => [
                static::BELONGS_TO,
                'Commerce_ProductTypeRecord',
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
            'discountId' => [AttributeType::Number, 'required' => true],
            'productTypeId' => [AttributeType::Number, 'required' => true],
        ];
    }

}