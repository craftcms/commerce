<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Dashboard\Widgets\Concerns;

use CraftCms\Cms\Ui\Controls\Choice;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Commerce\Helpers\Currency;
use CraftCms\Commerce\Order\OrderStatuses;
use CraftCms\Commerce\Payment\PaymentCurrencies;
use CraftCms\Commerce\Stats\Contracts\StatInterface;

use CraftCms\Commerce\Store\Concerns\StoreTrait;
use CraftCms\Commerce\Store\Stores;
use function CraftCms\Cms\t;

trait StatWidgetTrait
{
    use StoreTrait;

    public mixed $startDate = null;

    public mixed $endDate = null;

    public ?string $dateRange = null;

    public ?array $orderStatuses = null;

    /**
     * Normalizes stat date keys (`Y-m-d` for days, `Y-n` for months) to ISO dates for charts.
     *
     * @param string[] $dateKeys
     * @return string[]
     */
    protected function chartDateLabels(array $dateKeys, string $interval): array
    {
        if ($interval !== 'month') {
            return array_values($dateKeys);
        }

        return array_map(static function(string $key): string {
            [$year, $month] = explode('-', $key);

            return sprintf('%s-%02d-01', $year, $month);
        }, array_values($dateKeys));
    }

    /**
     * Formats an amount in the primary payment currency for display in a widget.
     */
    protected function formatCurrency(float|int|string|null $amount): string
    {
        return Currency::formatAsCurrency($amount ?? 0, app(PaymentCurrencies::class)->getPrimaryPaymentCurrencyIso());
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    public function getOrderStatusOptions(): array
    {
        $orderStatuses = [];

        foreach (app(OrderStatuses::class)->getAllOrderStatuses($this->storeId) as $orderStatus) {
            $orderStatuses[] = [
                'label' => $orderStatus->name,
                'value' => $orderStatus->uid,
            ];
        }

        return $orderStatuses;
    }

    /**
     * @return array<int, array{label: string, value: int}>
     */
    public function getStoreOptions(): array
    {
        return app(Stores::class)->getAllStores()->map(fn($store) => [
            'label' => $store->getName(),
            'value' => $store->id,
        ])->all();
    }

    /**
     * Preset date-range options. `DATE_RANGE_CUSTOM` is intentionally not offered here -- the
     * legacy custom-range JS date picker isn't part of the new Ui control set, so only the
     * fixed presets are configurable going forward.
     *
     * @return array<int, array{label: string, value: string}>
     */
    public function getDateRangeOptions(): array
    {
        return [
            ['label' => t('Today', category: 'commerce'), 'value' => StatInterface::DATE_RANGE_TODAY],
            ['label' => t('This week', category: 'commerce'), 'value' => StatInterface::DATE_RANGE_THISWEEK],
            ['label' => t('This month', category: 'commerce'), 'value' => StatInterface::DATE_RANGE_THISMONTH],
            ['label' => t('This year', category: 'commerce'), 'value' => StatInterface::DATE_RANGE_THISYEAR],
            ['label' => t('Past {num} days', ['num' => 7], category: 'commerce'), 'value' => StatInterface::DATE_RANGE_PAST7DAYS],
            ['label' => t('Past {num} days', ['num' => 30], category: 'commerce'), 'value' => StatInterface::DATE_RANGE_PAST30DAYS],
            ['label' => t('Past {num} days', ['num' => 90], category: 'commerce'), 'value' => StatInterface::DATE_RANGE_PAST90DAYS],
            ['label' => t('Past year', category: 'commerce'), 'value' => StatInterface::DATE_RANGE_PASTYEAR],
            ['label' => t('All', category: 'commerce'), 'value' => StatInterface::DATE_RANGE_ALL],
        ];
    }

    /**
     * The store/date-range/order-statuses fields shared by every stat-backed widget's settings form.
     *
     * @return Field[]
     */
    protected function statSettingsFields(): array
    {
        return [
            Field::make(t('Store', category: 'commerce'))
                ->control(Choice::make('storeId')->value($this->storeId)->options($this->getStoreOptions())),
            Field::make(t('Date Range', category: 'app'))
                ->control(Choice::make('dateRange')->value($this->dateRange)->options($this->getDateRangeOptions())),
            Field::make(t('Order Statuses', category: 'commerce'))
                ->instructions(t('Only orders with the following order statuses will be included. Leave blank to include all statuses.', category: 'commerce'))
                ->control(Choice::make('orderStatuses')->multiple()->value($this->orderStatuses)->options($this->getOrderStatusOptions())),
        ];
    }
}
