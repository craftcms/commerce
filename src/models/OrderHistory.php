<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use craft\commerce\base\Model;
use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use DateTime;

/**
 * Class Order History Class
 *
 * @property Customer $customer
 * @property OrderStatus $newStatus
 * @property Order $order
 * @property OrderStatus $prevStatus
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class OrderHistory extends Model
{
    // Properties
    // =========================================================================

    /**
     * @var int ID
     */
    public $id;

    /**
     * @var string Message
     */
    public $message;

    /**
     * @var int Order ID
     */
    public $orderId;

    /**
     * @var int Previous Status ID
     */
    public $prevStatusId;

    /**
     * @var int New status ID
     */
    public $newStatusId;

    /**
     * @var int Customer ID
     */
    public $customerId;

    /**
     * @var Datetime|null
     */
    public $dateCreated;

    // Public Methods
    // =========================================================================

    /**
     * @return Order|null
     */
    public function getOrder()
    {
        return Plugin::getInstance()->getOrders()->getOrderById($this->orderId);
    }

    /**
     * @return OrderStatus|null
     */
    public function getPrevStatus()
    {
        return Plugin::getInstance()->getOrderStatuses()->getOrderStatusById($this->prevStatusId);
    }

    /**
     * @return OrderStatus|null
     */
    public function getNewStatus()
    {
        return Plugin::getInstance()->getOrderStatuses()->getOrderStatusById($this->newStatusId);
    }

    /**
     * @return Customer|null
     */
    public function getCustomer()
    {
        return Plugin::getInstance()->getCustomers()->getCustomerById($this->customerId);
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['orderId', 'customerId'], 'required'],
        ];
    }
}

