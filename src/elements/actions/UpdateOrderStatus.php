<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\elements\actions;

use Craft;
use craft\base\ElementAction;
use craft\commerce\behaviors\StoreBehavior;
use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\Cp;
use craft\helpers\Json;
use craft\models\Site;

/**
 * Class Update Order Status
 *
 * @property null|string $triggerHtml the action’s trigger HTML
 * @property string $triggerLabel the action’s trigger label
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class UpdateOrderStatus extends ElementAction
{
    /**
     * @var int|null
     */
    public ?int $orderStatusId = null;

    /**
     * @var string
     */
    public string $message = '';

    /**
     * @var bool Whether to suppress the sending of related order status emails
     */
    public bool $suppressEmails = false;

    /**
     * @inheritdoc
     */
    public function getTriggerLabel(): string
    {
        return Craft::t('commerce', 'Update Order Status…');
    }

    /**
     * @inheritdoc
     */
    public function getTriggerHtml(): ?string
    {
        /** @var Site|StoreBehavior $cpSite */
        $cpSite = Cp::requestedSite();
        $storeOrderStatuses = Plugin::getInstance()->getOrderStatuses()->getAllOrderStatuses($cpSite->getStore()->id)->all();

        $orderStatuses = Json::encode(array_values($storeOrderStatuses));
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

        Craft::$app->getView()->registerJs($js);

        return null;
    }

    /**
     * @inheritdoc
     */
    public function performAction(ElementQueryInterface $query): bool
    {
        $orders = $query->all();
        $orderCount = count($orders);

        $failureCount = 0;
        foreach ($orders as $order) {
            /** @var Order $order */
            $order->orderStatusId = $this->orderStatusId;
            $order->message = $this->message;
            $order->suppressEmails = $this->suppressEmails;
            if (!Craft::$app->getElements()->saveElement($order)) {
                $failureCount++;
            }
        }

        if ($failureCount > 0) {
            $message = Craft::t('commerce', 'Failed updating order status on {num, plural, =1{order} other{orders}}.', ['num' => $failureCount]);
            if ($orderCount === $failureCount) {
                $message = Craft::t('commerce', 'Failed to update {num, plural, =1{order status} other{order statuses}}.', ['num' => $failureCount]);
            }

            $this->setMessage($message);
            return false;
        }

        $this->setMessage(Craft::t('commerce', '{num, plural, =1{Order} other{Orders}} updated.', ['num' => $orderCount]));

        return true;
    }
}
