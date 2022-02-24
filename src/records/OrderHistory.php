<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\records;

use craft\commerce\db\Table;
use craft\db\ActiveRecord;
use DateTime;
use yii\db\ActiveQueryInterface;

/**
 * Order history record.
 *
 * @property Customer $customer
 * @property int $customerId
 * @property DateTime $dateCreated
 * @property int $id
 * @property string $message
 * @property OrderStatus $newStatus
 * @property int $newStatusId
 * @property Order $order
 * @property int $orderId
 * @property OrderStatus $prevStatus
 * @property int $prevStatusId
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class OrderHistory extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::ORDERHISTORIES;
    }

    public function getOrder(): ActiveQueryInterface
    {
        return $this->hasOne(Order::class, ['id' => 'orderId']);
    }

    /**
     * @noinspection PhpUnused
     */
    public function getPrevStatus(): ActiveQueryInterface
    {
        return $this->hasOne(OrderStatus::class, ['id' => 'prevStatusId']);
    }

    /**
     * @noinspection PhpUnused
     */
    public function getNewStatus(): ActiveQueryInterface
    {
        return $this->hasOne(OrderStatus::class, ['id' => 'newStatusId']);
    }

    public function getCustomer(): ActiveQueryInterface
    {
        return $this->hasOne(Customer::class, ['id' => 'customerId']);
    }
}
