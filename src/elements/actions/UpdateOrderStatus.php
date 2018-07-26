<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\elements\actions;

use Craft;
use craft\base\ElementAction;
use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\Json;

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
    // Public Properties
    // =========================================================================

    /**
     * @var int
     */
    public $orderStatusId;

    /**
     * @var string
     */
    public $message;

    // Public Methods
    // =========================================================================

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
    public function getTriggerHtml()
    {
        $orderStatuses = Json::encode(array_values(Plugin::getInstance()->getOrderStatuses()->getAllOrderStatuses()));
        $type = Json::encode(static::class);

        $js = <<<EOT
(function()
{
    var trigger = new Craft.ElementActionTrigger({
        type: {$type},
        batch: true,
        activate: function(\$selectedItems)
        {
            Craft.elementIndex.setIndexBusy();
            var currentSourceStatusHandle = Craft.elementIndex.sourceKey.split(':')[1];
            var currentOrderStatus = null;
            var orderStatuses = {$orderStatuses};
            for (i = 0; i < orderStatuses.length; i++) {
                if(orderStatuses[i].handle == currentSourceStatusHandle){
                    currentOrderStatus = orderStatuses[i];
                }
            }
            var modal = new Craft.Commerce.UpdateOrderStatusModal(currentOrderStatus,orderStatuses, {
                onSubmit: function(data){
                   Craft.elementIndex.submitAction({$type}, data);
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

        foreach ($orders as $order) {
            /** @var Order $order */
            $order->orderStatusId = $this->orderStatusId;
            $order->message = $this->message;
            Craft::$app->getElements()->saveElement($order);
        }

        return true;
    }
}
