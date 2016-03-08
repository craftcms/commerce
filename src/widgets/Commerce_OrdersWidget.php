<?php

namespace Craft;

class Commerce_OrdersWidget extends BaseWidget
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritDoc IComponentType::isSelectable()
     *
     * @return bool
     */
    public function isSelectable()
    {
        // This widget is only available to users that can manage orders
        return craft()->userSession->checkPermission('commerce-manageOrders');
    }

    /**
     * @inheritDoc IComponentType::getName()
     *
     * @return string
     */
    public function getName()
    {
        return Craft::t('Recent Orders');
    }

    /**
     * @inheritDoc IWidget::getIconPath()
     *
     * @return string
     */
    public function getIconPath()
    {
        return craft()->path->getPluginsPath().'commerce/resources/icon-mask.svg';
    }

    /**
     * @inheritDoc IWidget::getTitle()
     *
     * @return string
     */
    public function getTitle()
    {
        if ($orderStatusId = $this->getSettings()->orderStatusId)
        {
            $orderStatus = craft()->commerce_orderStatuses->getOrderStatusById($orderStatusId);

            if ($orderStatus)
            {
                return Craft::t('Recent Orders').' – '.Craft::t($orderStatus->name);
            }
        }

        return parent::getTitle();
    }

    /**
     * @inheritDoc IWidget::getBodyHtml()
     *
     * @return string|false
     */
    public function getBodyHtml()
    {
        $orders = $this->_getOrders();

        return craft()->templates->render('commerce/_components/widgets/Orders/body', array(
            'orders' => $orders,
            'showStatuses' => empty($this->getSettings()->orderStatusId)
        ));
    }

    /**
     * @inheritDoc ISavableComponentType::getSettingsHtml()
     *
     * @return string
     */
    public function getSettingsHtml()
    {
        $orderStatuses = craft()->commerce_orderStatuses->getAllOrderStatuses();

        craft()->templates->includeJsResource('commerce/js/CommerceOrdersWidgetSettings.js');

        $id = 'analytics-settings-'.StringHelper::randomString();
        $namespaceId = craft()->templates->namespaceInputId($id);

        craft()->templates->includeJs("new Craft.Commerce.OrdersWidgetSettings('".$namespaceId."');");

        return craft()->templates->render('commerce/_components/widgets/Orders/settings', array(
            'id' => $id,
            'settings' => $this->getSettings(),
            'orderStatuses' => $orderStatuses,
        ));
    }

    // Protected Methods
    // =========================================================================

    /**
     * Returns the recent entries, based on the widget settings and user permissions.
     *
     * @return array
     */
    private function _getOrders()
    {
        $orderStatusId = $this->getSettings()->orderStatusId;
        $limit = $this->getSettings()->limit;

        $criteria = craft()->elements->getCriteria('Commerce_Order');
        $criteria->completed = true;
        $criteria->dateOrdered = "NOT NULL";
        $criteria->limit = $limit;
        $criteria->order = 'dateOrdered desc';

        if($orderStatusId)
        {
            $criteria->orderStatusId = $orderStatusId;
        }

        return $criteria->find();
    }

    /**
     * @inheritDoc BaseSavableComponentType::defineSettings()
     *
     * @return array
     */
    protected function defineSettings()
    {
        return array(
            'orderStatusId'   => AttributeType::Number,
            'limit'   => array(AttributeType::Number, 'default' => 10),
        );
    }
}
