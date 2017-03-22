<?php
namespace craft\commerce\records;

use craft\db\ActiveRecord;

/**
 * Taz zone country
 *
 * @property int $customerId
 * @property int $addressId
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class CustomerAddress extends ActiveRecord
{

    /**
     * @return string
     */
    public static function tableName()
    {
        return "commerce_customers_addresses";
    }

//    /**
//     * @return array
//     */
//    public function defineIndexes()
//    {
//        return [
//            ['columns' => ['customerId']],
//            ['columns' => ['addressId']],
//            ['columns' => ['customerId', 'addressId'], 'unique' => true],
//        ];
//    }


//    /**
//     * @inheritDoc BaseRecord::defineRelations()
//     *
//     * @return array
//     */
//    public function defineRelations()
//    {
//        return [
//            'customer' => [
//                static::BELONGS_TO,
//                'Customer',
//                'onDelete' => self::CASCADE,
//                'onUpdate' => self::CASCADE,
//                'required' => true
//            ],
//            'address' => [
//                static::BELONGS_TO,
//                'Address',
//                'onDelete' => self::CASCADE,
//                'onUpdate' => self::CASCADE,
//                'required' => true
//            ],
//        ];
//    }

}
