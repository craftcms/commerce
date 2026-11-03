<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Dashboard\Widgets;

use CraftCms\Cms\Cp\RequestedSite;
use CraftCms\Cms\Dashboard\Widgets\Widget;
use CraftCms\Cms\Support\Arr;
use CraftCms\Cms\Support\DateTimeHelper;
use CraftCms\Cms\Ui\Controls\Choice;
use CraftCms\Cms\Ui\Controls\Lightswitch;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Cms\Ui\UiContext;
use CraftCms\Commerce\Dashboard\Widgets\Concerns\HasCommerceIcon;
use CraftCms\Commerce\Dashboard\Widgets\Concerns\StatWidgetTrait;
use CraftCms\Commerce\Helpers\Currency;
use CraftCms\Commerce\Payment\PaymentCurrencies;
use CraftCms\Commerce\Stats\Contracts\StatInterface;
use CraftCms\Commerce\Stats\TotalRevenue as TotalRevenueStat;
use CraftCms\Commerce\Store\Stores;
use Illuminate\Validation\Rule;

use function CraftCms\Cms\currentUser;
use function CraftCms\Cms\t;

class TotalRevenue extends Widget
{
    use HasCommerceIcon;
    use StatWidgetTrait;

    public string $type = TotalRevenueStat::TYPE_TOTAL;

    public bool $showOrderCount = false;

    private TotalRevenueStat $stat;

    /** @param array<string, mixed> $config */
    public function __construct(array $config = [])
    {
        parent::__construct($config);

        if (!$this->storeId) {
            /** @phpstan-ignore-next-line method.notFound (getStore() is added to Site via a Macroable macro registered in Plugin::registerBehaviorMacros(), not visible to static analysis) */
            $this->storeId = app(RequestedSite::class)->get()?->getStore()->id ?? app(Stores::class)->getPrimaryStore()->id;
        }

        $this->dateRange = $this->dateRange ?: StatInterface::DATE_RANGE_TODAY;

        $this->stat = new TotalRevenueStat(
            $this->dateRange,
            DateTimeHelper::toDateTime($this->startDate, true),
            DateTimeHelper::toDateTime($this->endDate, true),
            $this->storeId,
        );

        if (!empty($this->orderStatuses)) {
            $this->stat->setOrderStatuses($this->orderStatuses);
        }

        $this->stat->type = $this->type;
    }

    #[\Override]
    public function getRules(): array
    {
        return [
            'type' => ['required', Rule::in([TotalRevenueStat::TYPE_TOTAL, TotalRevenueStat::TYPE_TOTAL_PAID])],
        ];
    }

    #[\Override]
    public static function isSelectable(): bool
    {
        return currentUser()?->can('commerce-manageOrders') ?? false;
    }

    #[\Override]
    public static function displayName(): string
    {
        return t('Total Revenue', category: 'commerce');
    }

    #[\Override]
    public function getTitle(): ?string
    {
        $stats = $this->stat->get();
        $revenue = Arr::pluck($stats, 'revenue');
        $total = round(array_sum($revenue), 0, \RoundingMode::HalfTowardsZero);

        $formattedTotal = Currency::formatAsCurrency($total, $this->getStore()->getCurrency()->getCode(), false, true, true);

        return t('{total} in total revenue', ['total' => $formattedTotal], category: 'commerce');
    }

    #[\Override]
    public function getSubtitle(): ?string
    {
        return $this->stat->getDateRangeWording();
    }

    #[\Override]
    public function component(): ?string
    {
        return 'commerce:widget-chart';
    }

    #[\Override]
    public function props(): array
    {
        $stats = $this->stat->get() ?: [];
        $interval = $this->stat->getDateRangeInterval();

        $datasets = [
            ['label' => t('Revenue', category: 'commerce'), 'data' => array_map(floatval(...), Arr::pluck($stats, 'revenue')), 'format' => 'currency'],
        ];

        if ($this->showOrderCount) {
            array_unshift($datasets, ['label' => t('Order count', category: 'commerce'), 'data' => array_map(intval(...), Arr::pluck($stats, 'count')), 'format' => 'number']);
        }

        return [
            'type' => 'line',
            'labels' => $this->chartDateLabels(Arr::pluck($stats, 'datekey'), $interval),
            'interval' => $interval,
            'currency' => app(PaymentCurrencies::class)->getPrimaryPaymentCurrencyIso(),
            'showLegend' => $this->showOrderCount,
            'datasets' => $datasets,
        ];
    }

    #[\Override]
    public function settingsUi(UiContext $context = new UiContext()): ?Ui
    {
        return Ui::make([
            Field::make(t('Type', category: 'commerce'))
                ->control(Choice::make('type')->value($this->type)->options([
                    ['label' => t('Total', category: 'commerce'), 'value' => TotalRevenueStat::TYPE_TOTAL],
                    ['label' => t('Total Paid', category: 'commerce'), 'value' => TotalRevenueStat::TYPE_TOTAL_PAID],
                ])),
            Field::make(t('Show Order Count?', category: 'commerce'))
                ->control(Lightswitch::make('showOrderCount')->value($this->showOrderCount)),
            ...$this->statSettingsFields(),
        ]);
    }
}
