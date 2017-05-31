<?php

namespace craft\commerce\records;

use craft\db\ActiveRecord;

/**
 * Order hsitory record.
 *
 * @property int         $id
 * @property string      $message
 *
 * @property int         $orderId
 * @property int         $prevStatusId
 * @property int         $newStatusId
 * @property int         $customerId
 * @property \DateTime   $dateCreated
 *
 * @property Order       $order
 * @property OrderStatus $prevStatus
 * @property OrderStatus $newStatus
 * @property Customer    $customer
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class OrderHistory extends ActiveRecord
{
    /**
     * @return string
     */
    public static function tableName()
    {
        return '{{%commerce_orderhistories}}';
    }

//    /**
//     * @return array
//     */
//    public function defineRelations()
//    {
//        return [
//            'order' => [
//                static::BELONGS_TO,
//                'Order',
//                'required' => true,
//                'onDelete' => self::CASCADE,
//                'onUpdate' => self::CASCADE
//            ],
//            'prevStatus' => [
//                static::BELONGS_TO,
//                'OrderStatus',
//                'onDelete' => self::RESTRICT,
//                'onUpdate' => self::CASCADE
//            ],
//            'newStatus' => [
//                static::BELONGS_TO,
//                'OrderStatus',
//                'onDelete' => self::RESTRICT,
//                'onUpdate' => self::CASCADE
//            ],
//            'customer' => [
//                static::BELONGS_TO,
//                'Customer',
//                'required' => true,
//                'onDelete' => self::CASCADE,
//                'onUpdate' => self::CASCADE
//            ],
//        ];
//    }
//
//    /**
//     * @return array
//     */
//    protected function defineAttributes()
//    {
//        return [
//            'orderId' => [AttributeType::Number, 'required' => true],
//            'customerId' => [AttributeType::Number, 'required' => true],
//            'message' => [AttributeType::Mixed],
//        ];
//    }

}