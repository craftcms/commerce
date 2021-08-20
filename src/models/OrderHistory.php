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
use craft\helpers\ArrayHelper;
use DateTime;
use yii\base\InvalidConfigException;

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
    /**
     * @var int|null ID
     */
    public ?int $id = null;

    /**
     * @var string|null Message
     */
    public ?string $message = null;

    /**
     * @var int Order ID
     */
    public int $orderId;

    /**
     * @var int|null Previous Status ID
     */
    public ?int $prevStatusId;

    /**
     * @var int|null New status ID
     */
    public ?int $newStatusId;

    /**
     * @var int|null Customer ID
     */
    public ?int $customerId;

    /**
     * @var Datetime|null
     */
    public ?DateTime $dateCreated = null;

    /**
     * @return Order|null
     * @throws InvalidConfigException
     */
    public function getOrder(): ?Order
    {
        return Plugin::getInstance()->getOrders()->getOrderById($this->orderId);
    }

    /**
     * @return OrderStatus|null
     * @throws InvalidConfigException
     */
    public function getPrevStatus(): ?OrderStatus
    {
        $orderStatuses = Plugin::getInstance()->getOrderStatuses()->getAllOrderStatuses(true);
        return ArrayHelper::firstWhere($orderStatuses, 'id', $this->prevStatusId);
    }

    /**
     * @return OrderStatus|null
     * @throws InvalidConfigException
     */
    public function getNewStatus(): ?OrderStatus
    {
        $orderStatuses = Plugin::getInstance()->getOrderStatuses()->getAllOrderStatuses(true);
        return ArrayHelper::firstWhere($orderStatuses, 'id', $this->newStatusId);
    }

    /**
     * @return Customer|null
     * @throws InvalidConfigException
     */
    public function getCustomer(): ?Customer
    {
        return Plugin::getInstance()->getCustomers()->getCustomerById($this->customerId);
    }

    /**
     * @inheritdoc
     */
    public function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['orderId', 'customerId'], 'required'];

        return $rules;
    }
}

