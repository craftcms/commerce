<?php

namespace craft\commerce\records;

use craft\db\ActiveRecord;

/**
 * Order status email record.
 *
 * @property int         $orderStatusId
 * @property int         $emailId
 *
 * @property OrderStatus $orderStatus
 * @property Email       $email
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class OrderStatusEmail extends ActiveRecord
{
    /**
     * @return string
     */
    public static function tableName()
    {
        return "commerce_orderstatus_emails";
    }
//
//    /**
//     * @return array
//     */
//    public function defineRelations()
//    {
//        return [
//            'orderStatus' => [
//                static::BELONGS_TO,
//                'OrderStatus',
//                'required' => true,
//                'onDelete' => self::CASCADE,
//                'onUpdate' => self::CASCADE
//            ],
//            'email' => [
//                static::BELONGS_TO,
//                'Email',
//                'required' => true,
//                'onDelete' => self::CASCADE,
//                'onUpdate' => self::CASCADE
//            ],
//        ];
//    }

}