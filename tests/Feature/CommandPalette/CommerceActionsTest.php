<?php

declare(strict_types=1);

use CraftCms\Cms\Cp\CommandPalette\ActionRegistry;
use CraftCms\Commerce\CommandPalette\CommerceActions;

test('the plugin registers its actions with the command palette', function() {
    $items = app(ActionRegistry::class)->items(paletteUser([], admin: true));

    expect(collect($items)->pluck('id'))->toContain('commerce-create-store');
});

test('users without Commerce access get no actions', function() {
    expect(app(CommerceActions::class)->all(paletteUser(['commerce-manageOrders'])))->toBe([]);
});

test('create order actions need commerce-manageOrders', function() {
    $ids = fn(array $permissions) => collect(app(CommerceActions::class)->all(paletteUser(['accessPlugin-commerce', ...$permissions])))
        ->pluck('id')
        ->filter(fn($id) => str_starts_with($id, 'commerce-create-order-'));

    expect($ids([]))->toBeEmpty()
        ->and($ids(['commerce-manageOrders']))->not->toBeEmpty();
});

test('promotion actions follow their create permissions', function() {
    $permissions = ['accessPlugin-commerce', 'commerce-manageStoreSettings', 'commerce-createSales'];

    $hrefs = collect(app(CommerceActions::class)->all(paletteUser($permissions)))->pluck('href');

    expect($hrefs->filter(fn($href) => str_ends_with($href, '/sales/new')))->not->toBeEmpty()
        ->and($hrefs->filter(fn($href) => str_ends_with($href, '/discounts/new')))->toBeEmpty();
});

test('settings actions are only offered to admins', function() {
    $ids = fn() => collect(app(CommerceActions::class)->all(paletteUser(['accessPlugin-commerce'])))->pluck('id');
    expect($ids())->not->toContain('commerce-create-gateway');

    $admin = fn() => collect(app(CommerceActions::class)->all(paletteUser([], admin: true)))->pluck('id');
    expect($admin())->toContain('commerce-create-gateway');
});

test('store management and settings actions are search only', function() {
    $actions = collect(app(CommerceActions::class)->all(paletteUser([], admin: true)));

    expect($actions->firstWhere('id', 'commerce-create-gateway')->searchOnly)->toBeTrue()
        ->and($actions->first(fn($action) => str_starts_with($action->id, 'commerce-create-order-'))->searchOnly)->toBeFalse();
});
