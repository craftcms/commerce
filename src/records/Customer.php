<?php
namespace craft\commerce\records;

use craft\db\ActiveRecord;
use craft\records\User;

/**
 * Customer record.
 *
 * @property int       $id
 * @property string    $email
 * @property int       $userId
 * @property int       $lastUsedBillingAddressId
 * @property int       $lastUsedShippingAddressId
 *
 * @property Address[] $addresses
 * @property Order[]   $orders
 * @property User      $user
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class Customer extends ActiveRecord
{
    /**
     * Returns the name of the associated database table.
     *
     * @return string
     */
    public static function tableName()
    {
        return 'commerce_customers';
    }

    public function getAddresses()
    {
        return $this->hasMany(Address::class, ['customerId' => 'id']);
    }

//    /**
//     * @return array
//     */
//    public function defineRelations()
//    {
//        return [
//            'user' => [static::BELONGS_TO, 'User'],
//            'customersaddresses' => [
//                static::HAS_MANY,
//                'CustomerAddress',
//                'customerId'
//            ],
//            'addresses' => [
//                static::HAS_MANY,
//                'Address',
//                ['addressId' => 'id'],
//                'through' => 'customersaddresses'
//            ],
//            'orders' => [
//                static::HAS_MANY,
//                'Order',
//                'customerId'
//            ],
//        ];
//    }
//
//    /**
//     * @inheritDoc BaseRecord::defineAttributes()
//     *
//     * @return array
//     */
//    protected function defineAttributes()
//    {
//        return [
//            'email' => AttributeType::Email,
//            'lastUsedBillingAddressId' => AttributeType::Number,
//            'lastUsedShippingAddressId' => AttributeType::Number
//        ];
//    }
}
