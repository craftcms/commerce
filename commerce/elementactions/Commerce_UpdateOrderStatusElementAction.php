<?php
namespace Craft;

/**
 * Class Commerce_UpdateOrderStatusElementAction
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.elementactions
 * @since     1.0
 */
class Commerce_UpdateOrderStatusElementAction extends BaseElementAction
{

    // Public Methods
    // =========================================================================

    /**
     * @inheritDoc IComponentType::getName()
     *
     * @return string
     */
    public function getName()
    {
        return Craft::t('Update Order Status…');
    }

    /**
     * @inheritDoc IElementAction::isDestructive()
     *
     * @return bool
     */
    public function isDestructive()
    {
        return true;
    }

    /**
     * @inheritDoc IElementAction::getTriggerHtml()
     *
     * @return string|null
     */
    public function getTriggerHtml()
    {

        $orderStatuses = JsonHelper::encode(craft()->commerce_orderStatuses->getAll());

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

        craft()->templates->includeJsResource('commerce/js/classes/updateOrderStatusModal.js');
        craft()->templates->includeJs($js);
    }

    /**
     * @param ElementCriteriaModel $criteria
     * @return bool
     */
    public function performAction(ElementCriteriaModel $criteria)
    {
        $orders = $criteria->find();

        foreach ($orders as $order) {
            /** @var Commerce_OrderModel $order */
            $order->orderStatusId = $this->getParams()->orderStatusId;
            $order->message = $this->getParams()->message;
            craft()->commerce_orders->save($order);
        }

        return true;
    }

    /**
     * @inheritDoc BaseElementAction::defineParams()
     *
     * @return array
     */
    protected function defineParams()
    {
        return array(
            'orderStatusId' => AttributeType::Number,
            'message' => AttributeType::String,
        );
    }
}
