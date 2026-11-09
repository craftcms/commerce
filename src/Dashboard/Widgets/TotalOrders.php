<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Dashboard\Widgets;

use CraftCms\Cms\Cp\RequestedSite;
use CraftCms\Cms\Dashboard\Widgets\Widget;
use CraftCms\Cms\Support\Arr;
use CraftCms\Cms\Support\DateTimeHelper;
use CraftCms\Cms\Support\Facades\I18N;
use CraftCms\Cms\Ui\Controls\Lightswitch;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Cms\Ui\UiContext;
use CraftCms\Commerce\Dashboard\Widgets\Concerns\HasCommerceIcon;
use CraftCms\Commerce\Dashboard\Widgets\Concerns\StatWidgetTrait;
use CraftCms\Commerce\Payment\PaymentCurrencies;
use CraftCms\Commerce\Stats\Contracts\StatInterface;
use CraftCms\Commerce\Stats\TotalOrders as TotalOrdersStat;
use CraftCms\Commerce\Store\Stores;

use function CraftCms\Cms\currentUser;
use function CraftCms\Cms\t;

class TotalOrders extends Widget
{
    use HasCommerceIcon;
    use StatWidgetTrait;

    public mixed $showChart = null;

    private TotalOrdersStat $stat;

    /** @param array<string, mixed> $config */
    public function __construct(array $config = [])
    {
        parent::__construct($config);

        if (!$this->storeId) {
            $this->storeId = app(RequestedSite::class)->get()?->getStore()->id ?? app(Stores::class)->getPrimaryStore()->id;
        }

        $this->dateRange = $this->dateRange ?: StatInterface::DATE_RANGE_TODAY;

        $this->stat = new TotalOrdersStat(
            $this->dateRange,
            DateTimeHelper::toDateTime($this->startDate, true),
            DateTimeHelper::toDateTime($this->endDate, true),
            $this->storeId,
        );

        if (!empty($this->orderStatuses)) {
            $this->stat->setOrderStatuses($this->orderStatuses);
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
        return t('Total Orders', category: 'commerce');
    }

    #[\Override]
    public static function maxColspan(): ?int
    {
        return 1;
    }

    #[\Override]
    public function getTitle(): ?string
    {
        if (!$this->showChart) {
            return '';
        }

        $stats = $this->stat->get();
        $total = $stats['total'] ?? 0;
        $total = I18N::getFormatter()->asInteger($total);

        return t('{total} orders', ['total' => $total], category: 'commerce');
    }

    #[\Override]
    public function getSubtitle(): ?string
    {
        if (!$this->showChart) {
            return '';
        }

        return $this->stat->getDateRangeWording();
    }

    #[\Override]
    public function component(): ?string
    {
        return $this->showChart ? 'commerce:widget-chart' : 'commerce:widget-number-stat';
    }

    #[\Override]
    public function props(): array
    {
        $stats = $this->stat->get() ?: [];

        if ($this->showChart) {
            $chart = $stats['chart'] ?? [];

            return [
                'type' => 'line',
                'labels' => $this->chartDateLabels(Arr::pluck($chart, 'datekey'), $this->stat->getDateRangeInterval()),
                'interval' => $this->stat->getDateRangeInterval(),
                'currency' => app(PaymentCurrencies::class)->getPrimaryPaymentCurrencyIso(),
                'showLegend' => false,
                'datasets' => [
                    ['label' => t('Order count', category: 'commerce'), 'data' => array_map(intval(...), Arr::pluck($chart, 'total')), 'format' => 'number'],
                ],
            ];
        }

        $total = $stats['total'] ?? 0;

        return [
            'value' => I18N::getFormatter()->asInteger($total),
            'label' => t($total == 1 ? 'order' : 'orders', category: 'commerce'),
            'timeFrame' => $this->stat->getDateRangeWording(),
        ];
    }

    #[\Override]
    public function settingsUi(UiContext $context = new UiContext()): ?Ui
    {
        return Ui::make([
            Field::make(t('Show Chart?', category: 'commerce'))
                ->control(Lightswitch::make('showChart')->value((bool)$this->showChart)),
            ...$this->statSettingsFields(),
        ]);
    }
}
