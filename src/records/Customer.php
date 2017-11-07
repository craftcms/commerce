<?php

namespace craft\commerce\records;

use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

/**
 * Customer record.
 *
 * @property int               $id
 * @property int               $userId
 * @property int               $lastUsedBillingAddressId
 * @property int               $lastUsedShippingAddressId
 * @property Address[]         $addresses
 * @property CustomerAddress[] $customerAddresses
 * @property Order[]           $orders
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
class Customer extends ActiveRecord
{
    // Public Methods
    // =========================================================================

    /**
     * @return string
     */
    public static function tableName(): string
    {
        return '{{%commerce_customers}}';
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getCustomerAddresses(): ActiveQueryInterface
    {
        return $this->hasMany(CustomerAddress::class, ['customerId' => 'id']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getAddresses(): ActiveQueryInterface
    {
        return $this->hasMany(Address::class, ['id' => 'addressId'])->via('customerAddresses');
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getOrders(): ActiveQueryInterface
    {
        return $this->hasMany(Order::class, ['id' => 'customerId']);
    }
}
