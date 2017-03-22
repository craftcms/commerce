<?php
namespace craft\commerce\models;

use craft\commerce\base\Model;
use craft\commerce\elements\Order;

/**
 * Class Order History Class
 *
 * @property int         $id
 * @property string      $message
 *
 * @property int         $orderId
 * @property int         $prevStatusId
 * @property int         $newStatusId
 * @property int         $customerId
 * @property \DateTime   $dateCreated
 *
 * @property Order       $order
 * @property OrderStatus $prevStatus
 * @property OrderStatus $newStatus
 * @property Customer    $customer
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2017, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.commerce
 * @since     2.0
 */
class OrderHistory extends Model
{

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
     * @var \DateTime Date Created
     */
    public $dateCreated;

    /**
     * @return \craft\commerce\elements\Order|null
     */
    public function getOrder()
    {
        return Plugin::geInstance()->getOrders()->getOrderById($this->orderId);
    }

    /**
     * @return \craft\commerce\models\OrderStatus|null
     */
    public function getPrevStatus()
    {
        return Plugin::getInstance()->getOrderStatuses()->getOrderStatusById($this->prevStatusId);
    }

    /**
     * @return \craft\commerce\models\OrderStatus|null
     */
    public function getNewStatus()
    {
        return Plugin::getInstance()->getOrderStatuses()->getOrderStatusById($this->newStatusId);
    }

    /**
     * @return \craft\commerce\models\Customer|null
     */
    public function getCustomer()
    {
        return Plugin::getInstance()->getCustomers()->getCustomerById($this->customerId);
    }
}





