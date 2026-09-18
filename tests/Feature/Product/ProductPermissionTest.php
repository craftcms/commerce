<?php

declare(strict_types=1);

use CraftCms\Cms\User\Elements\User;
use CraftCms\Cms\User\UserPermissions;
use CraftCms\Commerce\Product\Elements\Product;
use CraftCms\Commerce\Product\ProductType\Data\ProductType;
use CraftCms\Commerce\Product\ProductType\ProductTypes;

/**
 * Stubs the acting user's granted permission strings for `Gate::after()`'s `UserPermissions`
 * lookup (see `AuthServiceProvider::registerPermissions()`), the same way
 * `VariantQueryAuthorizationTest` does, so `canView()`/`canSave()`/`canDuplicate()`/`canDelete()`
 * can be exercised against an exact, known permission set without going through permission
 * persistence.
 *
 * @param string[] $permissions
 */
function grantProductPermissionTestPermissions(array $permissions): void
{
    $mock = Mockery::mock(UserPermissions::class)->makePartial();
    $mock->shouldReceive('doesUserHavePermission')
        ->andReturnUsing(fn(int $userId, string $checkPermission): bool => in_array($checkPermission, $permissions, true));
    app()->instance(UserPermissions::class, $mock);
}

/**
 * Stubs `ProductTypes::getProductTypeById()` so `Product::getType()` resolves a known,
 * unpersisted product type with a fixed `uid` for the permission strings below to key off of.
 */
function stubProductType(int $id, string $uid): ProductType
{
    $productType = new ProductType();
    $productType->id = $id;
    $productType->uid = $uid;

    $mock = Mockery::mock(ProductTypes::class)->makePartial();
    $mock->shouldReceive('getProductTypeById')->andReturn($productType);
    app()->instance(ProductTypes::class, $mock);

    return $productType;
}

/** @return array{User, Product} */
function existingProduct(): array
{
    $productType = stubProductType(1, 'randomuid');

    $user = new User();
    $user->id = 1;
    $user->admin = false;

    $product = new Product();
    $product->id = 100;
    $product->typeId = $productType->id;

    return [$user, $product];
}

/** @return array{User, Product} */
function newProduct(): array
{
    $productType = stubProductType(1, 'randomuid');

    $user = new User();
    $user->id = 1;
    $user->admin = false;

    $product = new Product();
    $product->typeId = $productType->id;

    return [$user, $product];
}

test('canView returns false with no permissions', function() {
    [$user, $product] = existingProduct();
    grantProductPermissionTestPermissions([]);

    expect($product->canView($user))->toBeFalse();
});

test('canView returns true with view permission on the product\'s product type', function() {
    [$user, $product] = existingProduct();
    grantProductPermissionTestPermissions(['commerce-viewProductType:randomuid']);

    expect($product->canView($user))->toBeTrue();
});

test('canView returns false when the view permission is for a different product type', function() {
    [$user, $product] = existingProduct();
    grantProductPermissionTestPermissions(['commerce-viewProductType:anotherrandomuid']);

    expect($product->canView($user))->toBeFalse();
});

test('canView returns false with only a save permission', function() {
    [$user, $product] = existingProduct();
    grantProductPermissionTestPermissions(['commerce-saveProductType:randomuid']);

    expect($product->canView($user))->toBeFalse();
});

test('canSave returns true for an existing product with the save permission', function() {
    [$user, $product] = existingProduct();
    grantProductPermissionTestPermissions(['commerce-viewProductType:randomuid', 'commerce-saveProductType:randomuid']);

    expect($product->canSave($user))->toBeTrue();
});

test('canSave returns false for an existing product with only the view permission', function() {
    [$user, $product] = existingProduct();
    grantProductPermissionTestPermissions(['commerce-viewProductType:randomuid']);

    expect($product->canSave($user))->toBeFalse();
});

test('canSave returns false for an existing product with only the create permission', function() {
    [$user, $product] = existingProduct();
    grantProductPermissionTestPermissions(['commerce-viewProductType:randomuid', 'commerce-createProductType:randomuid']);

    expect($product->canSave($user))->toBeFalse();
});

test('canSave returns true for a new product with the create permission', function() {
    [$user, $product] = newProduct();
    grantProductPermissionTestPermissions(['commerce-viewProductType:randomuid', 'commerce-createProductType:randomuid']);

    expect($product->canSave($user))->toBeTrue();
});

test('canSave returns false for a new product with only the view permission', function() {
    [$user, $product] = newProduct();
    grantProductPermissionTestPermissions(['commerce-viewProductType:randomuid']);

    expect($product->canSave($user))->toBeFalse();
});

test('canSave returns false for a new product with only the save permission', function() {
    [$user, $product] = newProduct();
    grantProductPermissionTestPermissions(['commerce-viewProductType:randomuid', 'commerce-saveProductType:randomuid']);

    expect($product->canSave($user))->toBeFalse();
});

test('canDelete returns true with the delete permission', function() {
    [$user, $product] = existingProduct();
    grantProductPermissionTestPermissions(['commerce-viewProductType:randomuid', 'commerce-deleteProductType:randomuid']);

    expect($product->canDelete($user))->toBeTrue();
});

test('canDelete returns false with only the view permission', function() {
    [$user, $product] = existingProduct();
    grantProductPermissionTestPermissions(['commerce-viewProductType:randomuid']);

    expect($product->canDelete($user))->toBeFalse();
});

test('canDelete returns false with only the save permission', function() {
    [$user, $product] = existingProduct();
    grantProductPermissionTestPermissions(['commerce-viewProductType:randomuid', 'commerce-saveProductType:randomuid']);

    expect($product->canDelete($user))->toBeFalse();
});

test('canDuplicate returns true with both the create and save permissions', function() {
    [$user, $product] = existingProduct();
    grantProductPermissionTestPermissions([
        'commerce-viewProductType:randomuid',
        'commerce-createProductType:randomuid',
        'commerce-saveProductType:randomuid',
    ]);

    expect($product->canDuplicate($user))->toBeTrue();
});

test('canDuplicate returns false with only the create permission', function() {
    [$user, $product] = existingProduct();
    grantProductPermissionTestPermissions(['commerce-viewProductType:randomuid', 'commerce-createProductType:randomuid']);

    expect($product->canDuplicate($user))->toBeFalse();
});

test('canDuplicate returns false with only the save permission', function() {
    [$user, $product] = existingProduct();
    grantProductPermissionTestPermissions(['commerce-viewProductType:randomuid', 'commerce-saveProductType:randomuid']);

    expect($product->canDuplicate($user))->toBeFalse();
});

test('canCreateDrafts always returns true', function() {
    [$user, $product] = existingProduct();
    grantProductPermissionTestPermissions([]);

    expect($product->canCreateDrafts($user))->toBeTrue();
});

test('an admin user bypasses all product type permissions', function() {
    [, $product] = existingProduct();
    grantProductPermissionTestPermissions([]);

    $admin = new User();
    $admin->id = 1;
    $admin->admin = true;

    expect($product->canView($admin))->toBeTrue();
    expect($product->canSave($admin))->toBeTrue();
    expect($product->canDelete($admin))->toBeTrue();
    expect($product->canDuplicate($admin))->toBeTrue();
});
