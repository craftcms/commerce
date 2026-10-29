<?php

declare(strict_types=1);

use CraftCms\Cms\Cp\CommandPalette\SearchProviderRegistry;
use CraftCms\Commerce\CommandPalette\OrderSearchProvider;

test('the plugin registers element search providers for orders, products, and variants', function() {
    expect(array_keys(app(SearchProviderRegistry::class)->providers()))
        ->toContain('elements-order', 'elements-product', 'elements-variant');
});

test('the order search provider needs commerce-manageOrders', function() {
    $provider = app(SearchProviderRegistry::class)->providers()['elements-order'];

    expect($provider->isAvailable(paletteUser([])))->toBeFalse()
        ->and($provider->isAvailable(paletteUser(['commerce-manageOrders'])))->toBeTrue();
});

test('orders are searched with Commerce's own provider', function() {
    $provider = app(SearchProviderRegistry::class)->providers()['elements-order'];

    expect($provider)->toBeInstanceOf(OrderSearchProvider::class);
});
