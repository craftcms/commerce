<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Models;

use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use CraftCms\Cms\Component\Component;

class OrderNotice extends Component
{
    public ?int $id = null;

    public string $type;

    public string $attribute;

    public string $message;

    public ?int $orderId = null;

    private ?Order $_order = null;

    public function __toString(): string
    {
        return $this->message ?: '';
    }

    #[\Override]
    public function getRules(): array
    {
        return [
            'type' => ['required', 'string'],
            'message' => ['required', 'string'],
            'attribute' => ['required', 'string'],
            'orderId' => ['required', 'integer'],
        ];
    }

    public function setOrder(Order $order): void
    {
        $this->_order = $order;
        $this->orderId = $order->id;
    }

    public function getOrder(): ?Order
    {
        if (!isset($this->_order) && $this->orderId) {
            $this->_order = Plugin::getInstance()->getOrders()->getOrderById($this->orderId);
        }

        return $this->_order;
    }
}
