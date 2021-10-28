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
use yii\behaviors\AttributeTypecastBehavior;

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
            [['type', 'message', 'attribute', 'orderId'], 'required'],
            [['orderId'], 'integer'],
        ];
    }

    /**
     * @param Order $order
     * @return void
     */
    public function setOrder(Order $order): void
    {
        $this->_order = $order;
        $this->orderId = $order->id;
    }

    /**
     * @return Order|null
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
