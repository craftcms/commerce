<?php
namespace craft\commerce\widgets;

use Craft;
use craft\base\Widget;
use craft\commerce\elements\Order;

class Orders extends Widget
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritDoc
     */
    public static function isSelectable(): bool
    {
        // This widget is only available to users that can manage orders
        return Craft::$app->getUser()->checkPermission('commerce-manageOrders');
    }

    /**
     * @inheritDoc IComponentType::getName()
     *
     * @return string
     */
    public function getName()
    {
        return Craft::t('commerce', 'Recent Orders');
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
    public function getTitle(): string
    {
        if ($orderStatusId = $this->getSettings()->orderStatusId) {
            $orderStatus = Plugin::getInstance()->getOrderStatuses()->getOrderStatusById($orderStatusId);

            if ($orderStatus) {
                return Craft::t('commerce', 'Recent Orders').' – '.Craft::t($orderStatus->name);
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

        return Craft::$app->getView()->render('commerce/_components/widgets/Orders/body', [
            'orders' => $orders,
            'showStatuses' => empty($this->getSettings()->orderStatusId)
        ]);
    }

    /**
     * Returns the recent entries, based on the widget settings and user permissions.
     *
     * @return array
     */
    private function _getOrders()
    {
        $orderStatusId = $this->getSettings()->orderStatusId;
        $limit = $this->getSettings()->limit;

        $query = Order::find();
        $query->isCompleted(true);
        $query->dateOrdered('NOT NULL');
        $query->limit($limit);
        $query->orderBy('dateOrdered');

        if ($orderStatusId) {
            $query->orderStatusId($orderStatusId);
        }

        return $query->all();
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritDoc ISavableComponentType::getSettingsHtml()
     *
     * @return string
     */
    public function getSettingsHtml()
    {
        $orderStatuses = Plugin::getInstance()->getOrderStatuses()->getAllOrderStatuses();

        Craft::$app->getView()->includeJsResource('commerce/js/CommerceOrdersWidgetSettings.js');

        $id = 'analytics-settings-'.StringHelper::randomString();
        $namespaceId = Craft::$app->getView()->namespaceInputId($id);

        Craft::$app->getView()->includeJs("new Craft.Commerce.OrdersWidgetSettings('".$namespaceId."');");

        return Craft::$app->getView()->render('commerce/_components/widgets/Orders/settings', [
            'id' => $id,
            'settings' => $this->getSettings(),
            'orderStatuses' => $orderStatuses,
        ]);
    }

    /**
     * @inheritDoc BaseSavableComponentType::defineSettings()
     *
     * @return array
     */
    protected function defineSettings()
    {
        return [
            'orderStatusId' => AttributeType::Number,
            'limit' => [AttributeType::Number, 'default' => 10],
        ];
    }
}
