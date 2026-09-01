<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Data;

use CraftCms\Cms\Component\Component;
use CraftCms\Cms\Support\Facades\Users;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\Orders;
use CraftCms\Commerce\Order\OrderStatuses;
use DateTime;

class OrderHistory extends Component
{
    public ?int $id = null;

    public ?string $message = null;

    public int $orderId;

    public ?int $prevStatusId = null;

    public ?int $newStatusId = null;

    public ?int $userId = null;

    public ?string $userName = '';

    public ?DateTime $dateCreated = null;

    private ?Order $_order = null;

    public function getOrder(): ?Order
    {
        if ($this->_order === null) {
            $this->_order = app(Orders::class)->getOrderById($this->orderId);
        }

        return $this->_order;
    }

    public function setOrder(Order $order): void
    {
        $this->_order = $order;
        $this->orderId = $order->id;
    }

    public function getPrevStatus(): ?OrderStatus
    {
        if ($this->prevStatusId === null) {
            return null;
        }

        $orderStatuses = app(OrderStatuses::class)->getAllOrderStatuses($this->getOrder()?->storeId);

        return collect($orderStatuses)->first(fn($status) => $status->id === $this->prevStatusId);
    }

    public function getNewStatus(): ?OrderStatus
    {
        if ($this->newStatusId === null) {
            return null;
        }

        $orderStatuses = app(OrderStatuses::class)->getAllOrderStatuses($this->getOrder()?->storeId);

        return collect($orderStatuses)->first(fn($status) => $status->id === $this->newStatusId);
    }

    public function getUser(): ?User
    {
        if ($this->userId === null) {
            return null;
        }

        return Users::getUserById($this->userId);
    }

    #[\Override]
    public function getRules(): array
    {
        return [
            'orderId' => ['required', 'integer'],
        ];
    }
}
