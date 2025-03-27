<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use Craft;
use craft\commerce\base\Model;
use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use craft\elements\User;
use craft\helpers\ArrayHelper;
use DateTime;
use yii\base\InvalidConfigException;

/**
 * Class Order History Class
 *
 * @property User $user
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
    public ?int $prevStatusId = null;

    /**
     * @var int|null New status ID
     */
    public ?int $newStatusId = null;

    /**
     * @var int|null User ID
     */
    public ?int $userId = null;

    /**
     * @var string|null User name or email
     */
    public ?string $userName = '';

    /**
     * @var Datetime|null
     */
    public ?DateTime $dateCreated = null;

    /**
     * @throws InvalidConfigException
     */
    public function getOrder(): ?Order
    {
        return Plugin::getInstance()->getOrders()->getOrderById($this->orderId);
    }

    /**
     * @throws InvalidConfigException
     */
    public function getPrevStatus(): ?OrderStatus
    {
        $orderStatuses = Plugin::getInstance()->getOrderStatuses()->getAllOrderStatuses($this->getOrder()?->storeId);
        return ArrayHelper::firstWhere($orderStatuses, 'id', $this->prevStatusId);
    }

    /**
     * @throws InvalidConfigException
     */
    public function getNewStatus(): ?OrderStatus
    {
        $orderStatuses = Plugin::getInstance()->getOrderStatuses()->getAllOrderStatuses($this->getOrder()?->storeId);
        return ArrayHelper::firstWhere($orderStatuses, 'id', $this->newStatusId);
    }

    /**
     * @return User|null
     */
    public function getUser(): ?User
    {
        if ($this->userId === null) {
            return null;
        }

        return Craft::$app->getUsers()->getUserById($this->userId);
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return [
            [['orderId', 'userId'], 'required'],
        ];
    }
}
