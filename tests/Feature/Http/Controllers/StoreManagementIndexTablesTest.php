<?php

declare(strict_types=1);

use CraftCms\Cms\Support\Url;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Store\Stores;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function() {
    $this->withoutVite();
    actingAs(User::find()->admin(true)->one());
    prioritizeCommerceRoutes();
});

it('renders a searchable index table with a column menu', function(string $path) {
    $storeHandle = app(Stores::class)->getPrimaryStore()->handle;

    get(Url::cpUrl("commerce/store-management/$storeHandle/$path"))
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page) => $page
            ->component('Ui', false)
            ->where('ui.nodes.0.component', 'craft:admin-table')
            ->where('ui.nodes.0.props.searchable', true)
            ->where('ui.nodes.0.props.columnsToggleable', true)
            ->etc());
})->with([
    'shipping methods' => 'shippingmethods',
    'shipping zones' => 'shippingzones',
    'shipping categories' => 'shippingcategories',
    'tax rates' => 'taxrates',
    'tax zones' => 'taxzones',
    'tax categories' => 'taxcategories',
    'payment currencies' => 'payment-currencies',
]);
