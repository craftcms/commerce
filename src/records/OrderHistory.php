<?php

namespace craft\commerce\records;

use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

/**
 * Order history record.
 *
 * @property int         $id
 * @property string      $message
 * @property int         $orderId
 * @property int         $prevStatusId
 * @property int         $newStatusId
 * @property int         $customerId
 * @property \DateTime   $dateCreated
 * @property Order       $order
 * @property OrderStatus $prevStatus
 * @property OrderStatus $newStatus
 * @property Customer    $customer
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
class OrderHistory extends ActiveRecord
{
    // Public Methods
    // =========================================================================

    /**
     * @return string
     */
    public static function tableName(): string
    {
        return '{{%commerce_orderhistories}}';
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getOrder(): ActiveQueryInterface
    {
        return $this->hasOne(Order::class, ['id' => 'orderId']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getPrevStatus(): ActiveQueryInterface
    {
        return $this->hasOne(OrderStatus::class, ['id' => 'prevStatusId']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getNewStatus(): ActiveQueryInterface
    {
        return $this->hasOne(OrderStatus::class, ['id' => 'newStatusId']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getCustomer(): ActiveQueryInterface
    {
        return $this->hasOne(Customer::class, ['id' => 'customerId']);
    }
}
