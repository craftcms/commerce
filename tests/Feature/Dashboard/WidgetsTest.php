<?php

declare(strict_types=1);

use CraftCms\Cms\View\HtmlStack;
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
use CraftCms\Commerce\Plugin;
use CraftCms\Commerce\Stats\Contracts\StatInterface;
use CraftCms\Commerce\Tests\Support\OrdersFixture;

test('dashboard widgets render with the assets their charts and tables need', function(string $class, array $config) {
    OrdersFixture::seed();

    $widget = new $class(['dateRange' => StatInterface::DATE_RANGE_ALL, ...$config]);

    // The dashboard renders widgets the same way: assets registered while rendering are captured with the HTML.
    $fragment = app(HtmlStack::class)->capture(fn() => $widget->props()['html'] ?? '');
    $assets = $fragment->headHtml . $fragment->bodyHtml;

    expect($fragment->html)->not->toBeEmpty()
        ->and(file_exists($widget::icon()))->toBeTrue()
        ->and($widget->settingsUi())->not->toBeNull();

    if (str_contains($fragment->html, '<canvas')) {
        expect($assets)
            ->toContain('vendor/craftcms/commerce/legacy/statwidgets/CommerceChart.js')
            ->toContain('vendor/craftcms/commerce/legacy/chartjs/Chart.bundle.min.js')
            ->toContain('vendor/craftcms/commerce/legacy/deepmerge/umd.js')
            ->toContain('window.commerceCurrency');
    }

    if (str_contains($assets, 'Craft.VueAdminTable')) {
        expect($assets)->toContain('legacy/admintable/dist/js/app.js');
    }
})->with([
    'average order total' => [AverageOrderTotal::class, []],
    'new customers' => [NewCustomers::class, []],
    'recent orders' => [Orders::class, []],
    'repeat customers' => [RepeatCustomers::class, []],
    'top customers' => [TopCustomers::class, []],
    'top products' => [TopProducts::class, []],
    'top product types' => [TopProductTypes::class, []],
    'top purchasables' => [TopPurchasables::class, []],
    'total orders' => [TotalOrders::class, ['showChart' => true]],
    'total orders by country' => [TotalOrdersByCountry::class, []],
    'total revenue' => [TotalRevenue::class, []],
]);

test('the dashboard widget assets are published from directories that exist', function() {
    $publishables = (new ReflectionProperty(Plugin::class, 'publishables'))->getValue(Plugin::getInstance());

    expect($publishables)->not->toBeEmpty();

    foreach (array_keys($publishables) as $source) {
        expect(is_dir($source))->toBeTrue("$source is missing");
    }
});
