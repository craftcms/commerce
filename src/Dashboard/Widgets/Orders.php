<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Dashboard\Widgets;

use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use craft\commerce\web\assets\commercewidgets\CommerceWidgetsAsset;
use craft\commerce\web\assets\orderswidget\OrdersWidgetAsset;
use craft\helpers\ArrayHelper;
use craft\helpers\Cp;
use craft\helpers\StringHelper;
use CraftCms\Cms\Dashboard\Widgets\Widget;
use CraftCms\Cms\Form\Controls\Choice;
use CraftCms\Cms\Form\Controls\Number;
use CraftCms\Cms\Form\Form;
use CraftCms\Cms\Form\FormContext;
use CraftCms\Cms\Form\Nodes\Field;
use CraftCms\Cms\View\TemplateMode;
use CraftCms\Commerce\Dashboard\Widgets\Concerns\StatWidgetTrait;

use function CraftCms\Cms\currentUser;
use function CraftCms\Cms\t;
use function CraftCms\Cms\template;

class Orders extends Widget
{
    use StatWidgetTrait;

    public int $limit = 10;

    /** @param array<string, mixed> $config */
    public function __construct(array $config = [])
    {
        parent::__construct($config);

        if (!$this->storeId) {
            $this->storeId = Cp::requestedSite()->getStore()->id;
        }
    }

    #[\Override]
    public static function isSelectable(): bool
    {
        return currentUser()?->can('commerce-manageOrders') ?? false;
    }

    #[\Override]
    public static function displayName(): string
    {
        return t('Recent Orders', category: 'commerce');
    }

    #[\Override]
    public static function icon(): ?string
    {
        return \Craft::getAlias('@craft/commerce/icon-mask.svg');
    }

    #[\Override]
    public function getTitle(): ?string
    {
        if (!empty($this->orderStatuses) && count($this->orderStatuses) === 1) {
            $orderStatus = Plugin::getInstance()->getOrderStatuses()->getOrderStatusByUid(ArrayHelper::firstValue($this->orderStatuses), $this->storeId);

            if ($orderStatus) {
                return t('Recent Orders', category: 'commerce') . ' – ' . t($orderStatus->name, category: 'commerce');
            }
        }

        return parent::getTitle();
    }

    #[\Override]
    public function getBodyHtml(): ?string
    {
        $orders = $this->getOrders();

        $id = 'recent-orders-settings-' . StringHelper::randomString();
        $namespaceId = \Craft::$app->getView()->namespaceInputId($id);

        return template('commerce/_components/widgets/orders/recent/body', [
            'orders' => $orders,
            'showStatuses' => !empty($this->orderStatuses) && count($this->orderStatuses) > 1,
            'id' => $id,
            'namespaceId' => $namespaceId,
        ], TemplateMode::Cp);
    }

    #[\Override]
    public function settingsForm(FormContext $context = new FormContext()): ?Form
    {
        \Craft::$app->getView()->registerAssetBundle(OrdersWidgetAsset::class);
        \Craft::$app->getView()->registerAssetBundle(CommerceWidgetsAsset::class);

        return Form::make([
            Field::make(t('Store', category: 'commerce'))
                ->control(Choice::make('storeId')->value($this->storeId)->options($this->getStoreOptions())),
            Field::make(t('Order Statuses', category: 'commerce'))
                ->instructions(t('Only orders with the following order statuses will be included. Leave blank to include all statuses.', category: 'commerce'))
                ->control(Choice::make('orderStatuses')->multiple()->value($this->orderStatuses)->options($this->getOrderStatusOptions())),
            Field::make(t('Limit', category: 'commerce'))
                ->control(Number::make('limit')->value($this->limit)->min(1)),
        ]);
    }

    /**
     * Returns the recent entries, based on the widget settings and user permissions.
     *
     * @return Order[]
     */
    private function getOrders(): array
    {
        $query = Order::find();
        $query->isCompleted(true);
        $query->dateOrdered(':notempty:');
        $query->limit($this->limit);
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
