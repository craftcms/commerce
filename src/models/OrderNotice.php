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
use yii\base\InvalidConfigException;

/**
 * Order notice model.
 *
 * @property Order|null $order
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.3
 */
class OrderNotice extends Model
{
    public const NOTICE_TYPE_CUSTOMER = 'customer';
    public const NOTICE_TYPE_ADMIN = 'admin';

    /**
     * @var int|null ID
     */
    public ?int $id = null;

    /**
     * @var string Type
     */
    public string $type;

    /**
     * @var string Attribute
     */
    public string $attribute;

    /**
     * @var string Message
     */
    public string $message;

    /**
     * @var int|null Order ID
     */
    public ?int $orderId = null;

    /**
     * @var string Whether this notice is for customers or admins only.
     * @since 5.7.0
     */
    public string $noticeType = self::NOTICE_TYPE_CUSTOMER;

    /**
     * @var Order|null The order this notice belongs to
     */
    private ?Order $_order = null;

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->message ?: '';
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return [
            [['id'], 'safe'],
            [['type', 'message', 'attribute', 'orderId'], 'required'],
            [['orderId'], 'integer'],
            [['noticeType'], 'in', 'range' => [self::NOTICE_TYPE_CUSTOMER, self::NOTICE_TYPE_ADMIN]],
        ];
    }

    public function setOrder(Order $order): void
    {
        $this->_order = $order;
        $this->orderId = $order->id;
    }

    /**
     * @throws InvalidConfigException
     */
    public function getOrder(): ?Order
    {
        if (!isset($this->_order) && $this->orderId) {
            $this->_order = Plugin::getInstance()->getOrders()->getOrderById($this->orderId);
        }

        return $this->_order;
    }
}
