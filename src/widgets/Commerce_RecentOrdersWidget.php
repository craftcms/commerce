<?php

namespace Craft;

class Commerce_RecentOrdersWidget extends BaseWidget
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
        return Craft::t('Commerce Recent Orders');
    }

    /**
     * @inheritDoc IWidget::getBodyHtml()
     *
     * @return string|false
     */
    public function getBodyHtml()
    {
        craft()->templates->includeJsResource('commerce/lib/d3/d3.js', true);
        craft()->templates->includeJsResource('commerce/js/classes/Chart.js', true);
        craft()->templates->includeJsResource('commerce/js/CommerceRecentOrdersWidget.js');

        $options = array();
        $options['dataUrl'] = UrlHelper::getActionUrl('commerce/reports/orders');
        $js = 'new Craft.CommerceRecentOrdersWidget('.$this->model->id.', '.JsonHelper::encode($options).');';
        craft()->templates->includeJs($js);

        craft()->templates->includeCssResource('commerce/charts.css');

        $orders = $this->_getOrders();

        return craft()->templates->render('commerce/_components/widgets/RecentOrders/body', array(
            'orders' => $orders
        ));
    }

    /**
     * @inheritDoc ISavableComponentType::getSettingsHtml()
     *
     * @return string
     */
    public function getSettingsHtml()
    {
        return craft()->templates->render('commerce/_components/widgets/RecentOrders/settings', array(
            'settings' => $this->getSettings()
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
        $criteria = craft()->elements->getCriteria('Commerce_Order');
        $criteria->completed = true;
        $criteria->dateOrdered = "NOT NULL";
        $criteria->limit = $this->getSettings()->limit;
        $criteria->order = 'elements.dateCreated desc';

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
            'limit'   => array(AttributeType::Number, 'default' => 10),
        );
    }
}
