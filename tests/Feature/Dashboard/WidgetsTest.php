<?php

declare(strict_types=1);

use CraftCms\Commerce\Dashboard\Widgets\AverageOrderTotal;
use CraftCms\Commerce\Dashboard\Widgets\NewCustomers;
use CraftCms\Commerce\Dashboard\Widgets\Orders;
use CraftCms\Commerce\Dashboard\Widgets\RepeatCustomers;
use CraftCms\Commerce\Dashboard\Widgets\TopCustomers;
use CraftCms\Commerce\Dashboard\Widgets\TopProducts;
use CraftCms\Commerce\Dashboard\Widgets\TopProductTypes;
use CraftCms\Commerce\Dashboard\Widgets\TopPurchasables;
use CraftCms\Commerce\Dashboard\Widgets\TotalOrders;
use CraftCms\Commerce\Dashboard\Widgets\TotalOrdersByCountry;
use CraftCms\Commerce\Dashboard\Widgets\TotalRevenue;
use CraftCms\Commerce\Stats\Contracts\StatInterface;
use CraftCms\Commerce\Tests\Support\OrdersFixture;

test('dashboard widgets render through Commerce widget components', function(string $class, array $config, string $component) {
    OrdersFixture::seed();

    $widget = new $class(['dateRange' => StatInterface::DATE_RANGE_ALL, ...$config]);
    $props = $widget->props();

    expect($widget->component())->toBe($component)
        ->and(file_exists($widget::icon()))->toBeTrue()
        ->and($widget->settingsUi())->not->toBeNull()
        ->and(json_encode($props, JSON_THROW_ON_ERROR))->toBeString();

    match ($component) {
        'commerce:widget-number-stat' => expect($props)->toHaveKeys(['value', 'label', 'timeFrame'])
            ->and($props['value'])->toBeString()->not->toBeEmpty(),
        'commerce:widget-table' => expect($props['columns'])->not->toBeEmpty()
            ->and($props['columns'][0]['key'])->toBe('title')
            ->and($props['rows'])->toBeArray()
            ->each(fn($row) => $row->toHaveKey('title')),
        'commerce:widget-chart' => expect($props)->toHaveKeys(['type', 'labels', 'interval', 'currency', 'showLegend', 'datasets'])
            ->and($props['datasets'])->not->toBeEmpty()
            ->each(fn($dataset) => $dataset->data->toHaveCount(count($props['labels']))),
    };
})->with([
    'average order total' => [AverageOrderTotal::class, [], 'commerce:widget-number-stat'],
    'new customers' => [NewCustomers::class, [], 'commerce:widget-number-stat'],
    'recent orders' => [Orders::class, [], 'commerce:widget-table'],
    'repeat customers' => [RepeatCustomers::class, [], 'commerce:widget-number-stat'],
    'top customers' => [TopCustomers::class, [], 'commerce:widget-table'],
    'top products' => [TopProducts::class, [], 'commerce:widget-table'],
    'top product types' => [TopProductTypes::class, [], 'commerce:widget-table'],
    'top purchasables' => [TopPurchasables::class, [], 'commerce:widget-table'],
    'total orders' => [TotalOrders::class, [], 'commerce:widget-number-stat'],
    'total orders chart' => [TotalOrders::class, ['showChart' => true], 'commerce:widget-chart'],
    'total orders by country' => [TotalOrdersByCountry::class, [], 'commerce:widget-chart'],
    'total revenue' => [TotalRevenue::class, ['showOrderCount' => true], 'commerce:widget-chart'],
]);

test('monthly chart date keys are normalized to ISO dates', function() {
    $widget = new TotalRevenue(['dateRange' => StatInterface::DATE_RANGE_ALL]);
    $labels = (fn() => $this->chartDateLabels(['2026-1', '2026-11'], 'month'))->call($widget);

    expect($labels)->toBe(['2026-01-01', '2026-11-01']);
});
