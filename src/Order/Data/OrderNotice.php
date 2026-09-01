<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Data;

use CraftCms\Cms\Component\Component;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\Enums\OrderNoticeType;
use CraftCms\Commerce\Order\Orders;

/**
 * @property OrderNoticeType|string $noticeType
 */
class OrderNotice extends Component
{
    public ?int $id = null;

    public string $type;

    public string $attribute;

    public string $message;

    public ?int $orderId = null;

    private OrderNoticeType $_noticeType = OrderNoticeType::Customer;

    private ?Order $_order = null;

    public function __toString(): string
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

    #[\Override]
    public function getRules(): array
    {
        return [
            'type' => ['required', 'string'],
            'message' => ['required', 'string'],
            'attribute' => ['required', 'string'],
            'orderId' => ['required', 'integer'],
            'noticeType' => ['string'],
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
            $this->_order = app(Orders::class)->getOrderById($this->orderId);
        }

        return $this->_order;
    }
}
