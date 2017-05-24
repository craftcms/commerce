<?php

namespace craft\commerce\records;

use craft\db\ActiveRecord;
use craft\records\FieldLayout;

/**
 * Order settings record.
 *
 * @property int         $id
 * @property string      $name
 * @property string      $handle
 * @property int         $fieldLayoutId
 *
 * @property FieldLayout $fieldLayout
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class OrderSettings extends ActiveRecord
{
    /**
     * @return string
     */
    public static function tableName()
    {
        return 'commerce_ordersettings';
    }

//    /**
//     * @return array
//     */
//    public function defineIndexes()
//    {
//        return [
//            ['columns' => ['handle'], 'unique' => true],
//        ];
//    }
//
//    /**
//     * @return array
//     */
//    public function defineRelations()
//    {
//        return [
//            'fieldLayout' => [
//                static::BELONGS_TO,
//                'FieldLayout',
//                'onDelete' => static::SET_NULL
//            ]
//        ];
//    }
//
//    /**
//     * @return array
//     */
//    protected function defineAttributes()
//    {
//        return [
//            'name' => [AttributeType::Name, 'required' => true],
//            'handle' => [AttributeType::Handle, 'required' => true]
//        ];
//    }

}