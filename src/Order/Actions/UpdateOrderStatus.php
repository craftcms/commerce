<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Actions;

use CraftCms\Cms\Cp\RequestedSite;
use CraftCms\Cms\Element\Actions\ElementAction;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\HtmlStack;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\Support\Json;
use CraftCms\Commerce\Order\Data\OrderStatus;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Store\Stores;

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

    public function getTriggerHtml(): ?string
    {
        $site = app(RequestedSite::class)->get() ?? Sites::getCurrentSite();
        $store = app(Stores::class)->getStoreBySiteId($site->id);

        // TODO: migrate to app(OrderStatuses::class)->getAllOrderStatuses() once the service migrated to src/
        $orderStatuses = app(\craft\commerce\services\OrderStatuses::class)->getAllOrderStatuses($store?->id)
            ->map(function(OrderStatus $orderStatus) {
                // Encode for output in JS
                $orderStatus->name = htmlspecialchars($orderStatus->name ?? '', ENT_QUOTES);
                $orderStatus->color = htmlspecialchars($orderStatus->color, ENT_QUOTES);
                $orderStatus->description = htmlspecialchars($orderStatus->description ?? '', ENT_QUOTES);

                return $orderStatus;
            });

        $orderStatuses = Json::encode(array_values($orderStatuses->all()));
        $type = Json::encode(static::class);

        $js = <<<EOT
(function()
{
    var trigger = new Craft.ElementActionTrigger({
        type: $type,
        batch: true,
        activate: function(\$selectedItems)
        {
            Craft.elementIndex.setIndexBusy();
            var currentSourceStatusHandle = Craft.elementIndex.sourceKey.split(':')[1];
            var currentOrderStatus = null;
            var orderStatuses = $orderStatuses;
            for (i = 0; i < orderStatuses.length; i++) {
                if(orderStatuses[i].handle == currentSourceStatusHandle){
                    currentOrderStatus = orderStatuses[i];
                }
            }
            var modal = new Craft.Commerce.UpdateOrderStatusModal(currentOrderStatus,orderStatuses, {
                onSubmit: function(data){
                   Craft.elementIndex.submitAction($type, data);
                   modal.hide();
                   return false;
                }
            });
        }
    });
})();
EOT;

        HtmlStack::js($js);

        return null;
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
