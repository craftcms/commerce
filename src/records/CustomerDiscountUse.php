<?php
namespace craft\commerce\records;

use craft\db\ActiveRecord;

/**
 * Customer discount record.
 *
 * @property int      $id
 * @property int      $discountId
 * @property int      $customerId
 * @property Discount $discount
 * @property Customer $customer
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class CustomerDiscountUse extends ActiveRecord
{
    /**
     * @return string
     */
    public static function tableName()
    {
        return 'commerce_customer_discountuses';
    }

//    /**
//     * @return array
//     */
//    public function defineIndexes()
//    {
//        return [
//            ['columns' => ['customerId', 'discountId'], 'unique' => true],
//        ];
//    }
//
//    /**
//     * @return array
//     */
//    public function defineRelations()
//    {
//        return [
//            'discount' => [
//                static::BELONGS_TO,
//                'Discount',
//                'onDelete' => self::CASCADE,
//                'onUpdate' => self::CASCADE,
//                'required' => true
//            ],
//            'customer' => [
//                static::BELONGS_TO,
//                'Customer',
//                'onDelete' => self::CASCADE,
//                'onUpdate' => self::CASCADE,
//                'required' => true
//            ],
//        ];
//    }

//    /**
//     * @return array
//     */
//    protected function defineAttributes()
//    {
//        return [
//            'discountId' => [AttributeType::Number, 'required' => true],
//            'customerId' => [AttributeType::Number, 'required' => true],
//            'uses' => [
//                AttributeType::Number,
//                'required' => true,
//                'min' => 1
//            ],
//        ];
//    }
}