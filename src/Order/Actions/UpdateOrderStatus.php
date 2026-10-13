<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Actions;

use CraftCms\Cms\Element\Actions\ElementAction;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Commerce\Order\Elements\Order;

use function CraftCms\Cms\t;

class UpdateOrderStatus extends ElementAction
{
    public ?int $orderStatusId = null;

    public string $message = '';

    /**
     * Whether to suppress the sending of related order status emails
     */
    public bool $suppressEmails = false;

    public function getTriggerLabel(): string
    {
        return t('Update Order Status…', category: 'commerce');
    }

    public function performAction(ElementQueryInterface $query): bool
    {
        /** @var Order[] $orders */
        $orders = $query->all();
        $orderCount = count($orders);

        $failureCount = 0;
        foreach ($orders as $order) {
            $order->orderStatusId = $this->orderStatusId;
            $order->message = $this->message;
            $order->suppressEmails = $this->suppressEmails;
            if (!Elements::saveElement($order)) {
                $failureCount++;
            }
        }

        if ($failureCount > 0) {
            $message = t('Failed updating order status on {num, plural, =1{order} other{orders}}.', ['num' => $failureCount], category: 'commerce');
            if ($orderCount === $failureCount) {
                $message = t('Failed to update {num, plural, =1{order status} other{order statuses}}.', ['num' => $failureCount], category: 'commerce');
            }

            $this->setMessage($message);
            return false;
        }

        $this->setMessage(t('{num, plural, =1{Order} other{Orders}} updated.', ['num' => $orderCount], category: 'commerce'));

        return true;
    }
}
