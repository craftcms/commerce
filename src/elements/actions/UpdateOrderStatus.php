<?php
namespace craft\commerce\elements\actions;

use Craft;
use craft\base\ElementAction;
use craft\commerce\elements\Order;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\Json;
use craft\commerce\Plugin;

/**
 * Class Update Order Status
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.elementactions
 * @since     1.0
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
     * @inheritDoc
     */
    public function getName()
    {
        return Craft::t('commerce', 'Update Order Status…');
    }

    /**
     * @inheritDoc IElementAction::getTriggerHtml()
     *
     * @return string|null
     */
    public function getTriggerHtml()
    {

        $orderStatuses = Json::encode(Plugin::getInstance()->getOrderStatuses()->getAllOrderStatuses());

        $js = <<<EOT
(function()
{
    var trigger = new Craft.ElementActionTrigger({
        handle: 'Commerce_UpdateOrderStatus',
        batch: true,
        activate: function(\$selectedItems)
        {
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
                   Craft.elementIndex.submitAction('Commerce_UpdateOrderStatus', data);
                   modal.hide();
                }
            });
        }
    });
})();
EOT;

        Craft::$app->getView()->includeJsResource('commerce/js/CommerceUpdateOrderStatusModal.js');
        Craft::$app->getView()->includeJs($js);
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
            Plugin::getInstance()->getOrders()->saveOrder($order);
        }

        return true;
    }
}
