<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\widgets;

use Craft;
use craft\base\Widget;
use craft\commerce\base\StatWidgetTrait;
use craft\commerce\behaviors\StoreBehavior;
use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use craft\commerce\web\assets\commercewidgets\CommerceWidgetsAsset;
use craft\commerce\web\assets\orderswidget\OrdersWidgetAsset;
use craft\helpers\ArrayHelper;
use craft\helpers\Cp;
use craft\helpers\StringHelper;
use craft\models\Site;

/**
 * Class Orders
 *
 * @property string|false $bodyHtml the widget's body HTML
 * @property string $settingsHtml the component’s settings HTML
 * @property string $title the widget’s title
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Orders extends Widget
{
    use StatWidgetTrait;

    /**
     * @var int
     */
    public int $limit = 10;

    public function init(): void
    {
        parent::init();

        if (!(isset($this->storeId)) || !$this->storeId) {
            /** @var Site|StoreBehavior $site */
            $site = Cp::requestedSite();
            $this->storeId = $site->getStore()->id;
        }
    }

    /**
     * @inheritdoc
     */
    public static function isSelectable(): bool
    {
        return Craft::$app->getUser()->checkPermission('commerce-manageOrders');
    }

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('commerce', 'Recent Orders');
    }

    /**
     * @inheritdoc
     */
    public static function icon(): ?string
    {
        return Craft::getAlias('@craft/commerce/icon-mask.svg');
    }

    /**
     * @inheritdoc
     */
    public function getTitle(): ?string
    {
        if (!empty($this->orderStatuses) && count($this->orderStatuses) === 1) {
            $orderStatus = Plugin::getInstance()->getOrderStatuses()->getOrderStatusByUid(ArrayHelper::firstValue($this->orderStatuses), $this->storeId);

            if ($orderStatus) {
                return Craft::t('commerce', 'Recent Orders') . ' – ' . Craft::t('commerce', $orderStatus->name);
            }
        }

        return parent::getTitle();
    }

    /**
     * @inheritdoc
     */
    public function getBodyHtml(): ?string
    {
        $orders = $this->_getOrders();

        $id = 'recent-orders-settings-' . StringHelper::randomString();
        $namespaceId = Craft::$app->getView()->namespaceInputId($id);


        return Craft::$app->getView()->renderTemplate('commerce/_components/widgets/orders/recent/body', [
            'orders' => $orders,
            'showStatuses' => !empty($this->orderStatuses) && count($this->orderStatuses) > 1,
            'id' => $id,
            'namespaceId' => $namespaceId,
        ]);
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml(): ?string
    {
        Craft::$app->getView()->registerAssetBundle(OrdersWidgetAsset::class);
        Craft::$app->getView()->registerAssetBundle(CommerceWidgetsAsset::class);

        $id = 'recent-orders-settings-' . StringHelper::randomString();
        $namespaceId = Craft::$app->getView()->namespaceInputId($id);

        return Craft::$app->getView()->renderTemplate('commerce/_components/widgets/orders/recent/settings', [
            'id' => $id,
            'widget' => $this,
            'orderStatuses' => $this->getOrderStatusOptions(),
            'namespaceId' => $namespaceId,
        ]);
    }


    /**
     * Returns the recent entries, based on the widget settings and user permissions.
     *
     * @return Order[]
     */
    private function _getOrders(): array
    {
        $limit = $this->limit;

        $query = Order::find();
        $query->isCompleted(true);
        $query->dateOrdered(':notempty:');
        $query->limit($limit);
        $query->storeId($this->storeId);
        $query->orderBy('dateOrdered DESC');

        if (!empty($this->orderStatuses)) {
            $orderStatusIds = Plugin::getInstance()->getOrderStatuses()->getAllOrderStatuses($this->storeId)
                ->filter(fn($orderStatus) => in_array($orderStatus->uid, $this->orderStatuses))->map(fn($os) => $os->id)->all();
            $query->orderStatusId($orderStatusIds);
        }

        return $query->all();
    }
}
