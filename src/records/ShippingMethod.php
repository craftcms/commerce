<?php
namespace craft\commerce\records;

use craft\db\ActiveRecord;

/**
 * Shipping method record.
 *
 * @property int            $id
 * @property string         $name
 * @property string         $handle
 * @property bool           $enabled
 *
 * @property ShippingRule[] $rules
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class ShippingMethod extends ActiveRecord
{
    /**
     * @return string
     */
    public static function tableName()
    {
        return 'commerce_shippingmethods';
    }

//    /**
//     * @return array
//     */
//    public function defineIndexes()
//    {
//        return [
//            ['columns' => ['name'], 'unique' => true],
//        ];
//    }

//    /**
//     * @return array
//     */
//    public function defineRelations()
//    {
//        return [
//            'rules' => [
//                self::HAS_MANY,
//                'ShippingRule',
//                'methodId',
//                'order' => 'rules.priority'
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
//            'enabled' => [
//                AttributeType::Bool,
//                'required' => true,
//                'default' => 1
//            ]
//        ];
//    }
}
