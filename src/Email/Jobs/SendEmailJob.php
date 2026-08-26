<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Email\Jobs;

use CraftCms\Cms\Queue\Job;
use CraftCms\Commerce\Email\Emails;
use CraftCms\Commerce\Email\Exceptions\EmailException;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\OrderHistories;

class SendEmailJob extends Job
{
    public int $timeout = 60;

    public int $tries = 5;

    public function __construct(
        public readonly int $orderId,
        public readonly array $orderData,
        public readonly int $commerceEmailId,
        public readonly int $orderHistoryId,
    ) {
        parent::__construct();
    }

    public function handle(): void
    {
        $this->setProgress(20);

        $order = $this->getOrder();

        if (!$order) {
            throw new \InvalidArgumentException('Invalid order ID: ' . $this->orderId);
        }

        $email = app(Emails::class)->getEmailById($this->commerceEmailId, $order->getStore()->id);

        if (!$email) {
            throw new \InvalidArgumentException('Invalid email ID: ' . $this->commerceEmailId);
        }

        $orderHistory = app(OrderHistories::class)->getOrderHistoryById($this->orderHistoryId);
        $this->setProgress(50);

        $error = '';

        if (!app(Emails::class)->sendEmail($email, $order, $orderHistory, $this->orderData, $error)) {
            throw new EmailException($error);
        }

        $this->setProgress(100);
    }

    #[\Override]
    protected function defaultDescription(): string
    {
        return 'Sending email for order ' . $this->getOrder()?->reference;
    }

    private function getOrder(): ?Order
    {
        return Order::find()->id($this->orderId)->one();
    }
}
