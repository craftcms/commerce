<?php

namespace craft\commerce\records;

use craft\db\ActiveRecord;

/**
 * Order status record.
 *
 * @property int     $id
 * @property string  $name
 * @property string  $handle
 * @property string  $color
 * @property int     $sortOrder
 * @property bool    $default
 *
 * @property Email[] $emails
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class OrderStatus extends ActiveRecord
{
    /**
     * @return string
     */
    public static function tableName()
    {
        return 'commerce_orderstatuses';
    }

//    /**
//     * @return array
//     */
//    public function defineRelations()
//    {
//        return [
//            'emails' => [
//                static::MANY_MANY,
//                'Email',
//                'commerce_orderstatus_emails(orderStatusId, emailId)'
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
//            'name' => [AttributeType::String, 'required' => true],
//            'handle' => [AttributeType::Handle, 'required' => true],
//            'color' => [AttributeType::Enum, 'values' => ['green', 'orange', 'red', 'blue', 'yellow', 'pink', 'purple', 'turquoise', 'light', 'grey', 'black'], 'required' => true, 'default' => 'green'],
//            'sortOrder' => AttributeType::Number,
//            'default' => [
//                AttributeType::Bool,
//                'default' => 0,
//                'required' => true
//            ],
//        ];
//    }
}