<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Dashboard\Widgets;

use CraftCms\Cms\Cp\RequestedSite;
use CraftCms\Cms\Dashboard\Widgets\Widget;
use CraftCms\Cms\Support\Arr;
use CraftCms\Cms\Support\Facades\I18N;
use CraftCms\Cms\Ui\Controls\Choice;
use CraftCms\Cms\Ui\Controls\Number;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Cms\Ui\UiContext;
use CraftCms\Commerce\Dashboard\Widgets\Concerns\HasCommerceIcon;
use CraftCms\Commerce\Dashboard\Widgets\Concerns\StatWidgetTrait;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\OrderStatuses;
use CraftCms\Commerce\Store\Stores;

use function CraftCms\Cms\currentUser;
use function CraftCms\Cms\t;

class Orders extends Widget
{
    use HasCommerceIcon;
    use StatWidgetTrait;

    public int $limit = 10;

    /** @param array<string, mixed> $config */
    public function __construct(array $config = [])
    {
        parent::__construct($config);

        if (!$this->storeId) {
            $this->storeId = app(RequestedSite::class)->get()?->getStore()->id ?? app(Stores::class)->getPrimaryStore()->id;
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
    public function getTitle(): ?string
    {
        if (!empty($this->orderStatuses) && count($this->orderStatuses) === 1) {
            $orderStatus = app(OrderStatuses::class)->getOrderStatusByUid(Arr::first($this->orderStatuses), $this->storeId);

            if ($orderStatus) {
                return t('Recent Orders – {orderStatus}', ['orderStatus' => $orderStatus->getUiLabel()], category: 'commerce');
            }
        }

        return parent::getTitle();
    }

    #[\Override]
    public function component(): ?string
    {
        return 'commerce:widget-table';
    }

    #[\Override]
    public function props(): array
    {
        $showStatuses = !empty($this->orderStatuses) && count($this->orderStatuses) > 1;

        return [
            'columns' => array_values(array_filter([
                ['key' => 'title', 'label' => t('Reference', category: 'commerce')],
                $showStatuses ? ['key' => 'orderStatus', 'label' => t('Status', category: 'commerce')] : null,
                ['key' => 'paid', 'label' => t('Total Paid', category: 'commerce')],
                ['key' => 'date', 'label' => t('Date Ordered', category: 'commerce')],
            ])),
            'rows' => array_map(fn(Order $order) => [
                'title' => $order->reference,
                'url' => $order->getCpEditUrl(),
                'orderStatus' => ($orderStatus = $order->getOrderStatus()) ? ['name' => $orderStatus->name, 'color' => $orderStatus->color] : null,
                'paid' => I18N::getFormatter()->asCurrency($order->getTotalPaid(), $order->currency),
                'date' => I18N::getFormatter()->asDatetime($order->dateOrdered, 'short'),
            ], $this->getOrders()),
        ];
    }

    #[\Override]
    public function settingsUi(UiContext $context = new UiContext()): ?Ui
    {
        return Ui::make([
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
            $orderStatusIds = app(OrderStatuses::class)->getAllOrderStatuses($this->storeId)
                ->filter(fn($orderStatus) => in_array($orderStatus->uid, $this->orderStatuses))->map(fn($os) => $os->id)->all();
            $query->orderStatusId($orderStatusIds);
        }

        return $query->all();
    }
}
