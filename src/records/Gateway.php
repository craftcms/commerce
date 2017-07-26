<?php

namespace craft\commerce\records;

use craft\commerce\Plugin;
use craft\db\ActiveRecord;

/**
 * Gateway record.
 *
 * @property int    $id
 * @property string $name
 * @property string $handle
 * @property string $paymentType
 * @property array  $settings
 * @property string $type
 * @property bool   $frontendEnabled
 * @property bool   $isArchived
 * @property bool   $dateArchived
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class Gateway extends ActiveRecord
{
    /**
     * The name of the table not including the craft db prefix e.g craft_
     *
     * @return string
     */
    public static function tableName()
    {
        return '{{%commerce_gateways}}';
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
//    protected function defineAttributes()
//    {
//        return [
//            'class' => [AttributeType::String, 'required' => true],
//            'name' => [AttributeType::String, 'required' => true],
//            'settings' => [AttributeType::Mixed],
//            'paymentType' => [
//                AttributeType::Enum,
//                'values' => ['authorize', 'purchase'],
//                'required' => true,
//                'default' => 'purchase'
//            ],
//            'frontendEnabled' => [
//                AttributeType::Bool,
//                'required' => true,
//                'default' => 0
//            ],
//            'isArchived' => [AttributeType::Bool, 'default' => false],
//            'dateArchived' => [AttributeType::DateTime],
//            'sortOrder' => [AttributeType::Number],
//        ];
//    }
}
