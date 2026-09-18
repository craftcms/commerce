<?php

declare(strict_types=1);

use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Cms\User\UserPermissions;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use CraftCms\Commerce\Tests\Support\ProductConditionsFixture;

/**
 * Stubs the acting user's granted permission strings for `Gate::after()`'s `UserPermissions`
 * lookup (see `AuthServiceProvider::registerPermissions()`), so `editable()`/`savable()` can be
 * exercised against an exact, known permission set without going through permission persistence.
 *
 * Stubs `doesUserHavePermission()` itself rather than `getPermissionsByUserId()`: the latter is
 * only reachable through `UserPermissions`'s own canonicalization helpers, which walk every
 * registered permission group and need constructor-injected state a bare partial mock never
 * initializes.
 *
 * @param string[] $permissions
 */
function grantVariantQueryTestPermissions(array $permissions): void
{
    $mock = Mockery::mock(UserPermissions::class)->makePartial();
    $mock->shouldReceive('doesUserHavePermission')
        ->andReturnUsing(fn(int $userId, string $checkPermission): bool => in_array($checkPermission, $permissions, true));
    app()->instance(UserPermissions::class, $mock);
}

beforeEach(function() {
    $this->fixture = ProductConditionsFixture::seed();

    $this->user = new User();
    $this->user->username = 'variant-query-non-admin';
    $this->user->email = 'variant-query-non-admin@crafttest.com';
    $this->user->admin = false;
    if (!Elements::saveElement($this->user)) {
        throw new RuntimeException('Could not save user: ' . json_encode($this->user->errors()->all()));
    }

    $this->actingAs($this->user, 'craft');
});

test('editable respects the current viewProductType permission', function() {
    grantVariantQueryTestPermissions(["commerce-viewProductType:{$this->fixture->tShirtsType->uid}"]);

    $ids = Variant::find()->editable(true)->ids();

    expect($ids)->toBe([$this->fixture->tShirtVariant->id]);
});

test('savable respects the current saveProductType permission', function() {
    grantVariantQueryTestPermissions(["commerce-saveProductType:{$this->fixture->tShirtsType->uid}"]);

    $ids = Variant::find()->savable(true)->ids();

    expect($ids)->toBe([$this->fixture->tShirtVariant->id]);
});

test('editable ignores a permission string outside the current viewProductType/saveProductType set', function() {
    grantVariantQueryTestPermissions(["commerce-editProductType:{$this->fixture->tShirtsType->uid}"]);

    $ids = Variant::find()->editable(true)->ids();

    expect($ids)->toBe([]);
});
