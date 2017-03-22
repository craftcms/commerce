<?php
namespace craft\commerce\records;

use craft\db\ActiveRecord;

/**
 * Sale user group record.
 *
 * @property int $id
 * @property int $saleId
 * @property int $userGroupId
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class SaleUserGroup extends ActiveRecord
{
    /**
     * @return string
     */
    public static function tableName()
    {
        return 'commerce_sale_usergroups';
    }

//    /**
//     * @return array
//     */
//    public function defineIndexes()
//    {
//        return [
//            ['columns' => ['saleId', 'userGroupId'], 'unique' => true],
//        ];
//    }
//
//    /**
//     * @return array
//     */
//    public function defineRelations()
//    {
//        return [
//            'sale' => [
//                static::BELONGS_TO,
//                'Sale',
//                'onDelete' => self::CASCADE,
//                'onUpdate' => self::CASCADE,
//                'required' => true
//            ],
//            'userGroup' => [
//                static::BELONGS_TO,
//                'UserGroup',
//                'onDelete' => self::CASCADE,
//                'onUpdate' => self::CASCADE,
//                'required' => true
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
//            'saleId' => [AttributeType::Number, 'required' => true],
//            'userGroupId' => [AttributeType::Number, 'required' => true],
//        ];
//    }

}