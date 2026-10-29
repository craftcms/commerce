<?php

declare(strict_types=1);

use CraftCms\Cms\Cp\CommandPalette\PageRegistry;
use CraftCms\Cms\User\Contracts\CraftUser;
use CraftCms\Commerce\CommandPalette\CommercePages;

/** @param list<string> $permissions */
function paletteUser(array $permissions, bool $admin = false): CraftUser
{
    $user = Mockery::mock(CraftUser::class);
    $user->shouldReceive('isAdmin')->andReturn($admin);
    $user->shouldReceive('can')->andReturnUsing(fn(string $permission) => $admin || in_array($permission, $permissions, true));

    return $user;
}

test('the plugin registers its pages with the command palette', function() {
    $items = app(PageRegistry::class)->items(paletteUser([], admin: true));

    expect(collect($items)->pluck('id'))->toContain('commerce-settings-general');
});

test('users without Commerce access get no pages', function() {
    expect(app(CommercePages::class)->all(paletteUser(['commerce-manageShipping'])))->toBe([]);
});

test('settings pages are only offered to admins', function() {
    $permissions = ['accessPlugin-commerce', 'commerce-manageStoreSettings', 'commerce-manageShipping'];

    $ids = collect(app(CommercePages::class)->all(paletteUser($permissions)))->pluck('id');
    expect($ids->filter(fn($id) => str_starts_with($id, 'commerce-settings-')))->toBeEmpty();

    $ids = collect(app(CommercePages::class)->all(paletteUser([], admin: true)))->pluck('id');
    expect($ids)->toContain('commerce-settings-gateways', 'commerce-settings-orders-statuses');
});

test('store management pages follow the permission for their area', function() {
    $permissions = ['accessPlugin-commerce', 'commerce-manageStoreSettings', 'commerce-manageTaxes'];

    $hrefs = collect(app(CommercePages::class)->all(paletteUser($permissions)))->pluck('href');

    expect($hrefs->filter(fn($href) => str_contains($href, '/taxrates')))->not->toBeEmpty()
        ->and($hrefs->filter(fn($href) => str_contains($href, '/shippingmethods')))->toBeEmpty()
        ->and($hrefs->filter(fn($href) => str_contains($href, '/sales')))->toBeEmpty();
});

test('store management pages need commerce-manageStoreSettings', function() {
    $hrefs = collect(app(CommercePages::class)->all(paletteUser(['accessPlugin-commerce', 'commerce-manageTaxes'])))->pluck('href');

    expect($hrefs->filter(fn($href) => str_contains($href, 'store-management')))->toBeEmpty();
});
