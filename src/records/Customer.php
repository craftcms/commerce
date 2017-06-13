<?php

namespace craft\commerce\records;

use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

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
 * @property CustomerAddress[] $customerAddresses
 * @property Order[]   $orders
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
    public static function tableName(): string
    {
        return '{{%commerce_customers}}';
    }

    public function getAddresses(): ActiveQueryInterface
    {
        return $this->hasMany(Address::class, ['id' => 'addressId'])->via('customerAddresses');
    }

    public function getCustomerAddresses(): ActiveQueryInterface
    {
        return $this->hasMany(CustomerAddress::class, ['customerId' => 'id']);
    }

    public function getOrders(): ActiveQueryInterface
    {
        return $this->hasMany(Order::class, ['id' => 'customerId']);
    }
}
