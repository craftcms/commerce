<?php

declare(strict_types=1);

use CraftCms\Cms\Support\Url;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Plugin;
use CraftCms\Commerce\Store\Stores;
use Illuminate\Support\Facades\Gate;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function() {
    $this->withoutVite();
    actingAs(User::find()->admin(true)->one());
    prioritizeCommerceRoutes();
});

it('redirects to the default view when the user has its permission', function() {
    app(Plugin::class)->getSettings()->defaultView = 'commerce/inventory';

    get(Url::cpUrl('commerce'))->assertRedirect(Url::cpUrl('commerce/inventory'));
});

it('redirects legacy default views to the primary store management area', function() {
    $handle = app(Stores::class)->getPrimaryStore()->handle;
    app(Plugin::class)->getSettings()->defaultView = 'commerce/tax/taxrates';

    get(Url::cpUrl('commerce'))->assertRedirect(Url::cpUrl("commerce/store-management/$handle/taxrates"));
});

it('falls back to the first section the user can access', function() {
    app(Plugin::class)->getSettings()->defaultView = 'commerce/inventory';
    Gate::before(fn($user, $ability) => in_array($ability, ['commerce-manageInventoryStockLevels', 'commerce-manageOrders']) ? false : null);

    get(Url::cpUrl('commerce'))->assertRedirect();
});

it('is forbidden when the user cannot access any section', function() {
    app(Plugin::class)->getSettings()->defaultView = 'commerce/orders';
    Gate::before(fn($user, $ability) => str_starts_with($ability, 'commerce-') ? false : null);
    app()->instance(CraftCms\Commerce\Product\ProductType\ProductTypes::class, Mockery::mock(CraftCms\Commerce\Product\ProductType\ProductTypes::class, ['getViewableProductTypes' => []]));

    get(Url::cpUrl('commerce'))->assertForbidden();
});
