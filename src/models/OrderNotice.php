<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use craft\commerce\base\Model;
use craft\commerce\elements\Order;
use craft\commerce\enums\OrderNoticeType;
use craft\commerce\Plugin;
use yii\base\InvalidConfigException;

/**
 * Order notice model.
 *
 * @property OrderNoticeType $noticeType
 * @property Order|null $order
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.3
 */
class OrderNotice extends Model
{
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
     * @var OrderNoticeType Whether this notice is for customers or admins only.
     * @since 5.7.0
     */
    private OrderNoticeType $_noticeType = OrderNoticeType::Customer;

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

    public function getNoticeType(): OrderNoticeType
    {
        return $this->_noticeType;
    }

    public function setNoticeType(string|OrderNoticeType $noticeType): void
    {
        $this->_noticeType = $noticeType instanceof OrderNoticeType
            ? $noticeType
            : OrderNoticeType::from($noticeType);
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return [
            [['id', 'noticeType'], 'safe'],
            [['type', 'message', 'attribute', 'orderId'], 'required'],
            [['orderId'], 'integer'],
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
