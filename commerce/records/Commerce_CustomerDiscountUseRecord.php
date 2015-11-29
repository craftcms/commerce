<?php
namespace Craft;

/**
 * Customer discount record.
 *
 * @property int $id
 * @property int $discountId
 * @property int $customerId
 * @property Commerce_DiscountRecord $discount
 * @property Commerce_CustomerRecord $customer
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class Commerce_CustomerDiscountUseRecord extends BaseRecord
{
    /**
     * @return string
     */
    public function getTableName()
    {
        return 'commerce_customer_discountuses';
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
            'customer' => [
                static::BELONGS_TO,
                'Commerce_CustomerRecord',
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
            'customerId' => [AttributeType::Number, 'required' => true],
            'uses' => [
                AttributeType::Number,
                'required' => true,
                'min' => 1
            ],
        ];
    }
}