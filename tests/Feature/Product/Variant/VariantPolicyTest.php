<?php

declare(strict_types=1);

use CraftCms\Cms\User\Elements\User;
use CraftCms\Cms\User\UserPermissions;
use CraftCms\Commerce\Product\Elements\Product;
use CraftCms\Commerce\Product\ProductType\Data\ProductType;
use CraftCms\Commerce\Product\ProductType\ProductTypes;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use CraftCms\Commerce\Product\Variant\Policies\VariantPolicy;
use Illuminate\Support\Facades\Gate;

/**
 * @param string[] $permissions
 */
function grantVariantPolicyTestPermissions(array $permissions): void
{
    $mock = Mockery::mock(UserPermissions::class)->makePartial();
    $mock->shouldReceive('doesUserHavePermission')
        ->andReturnUsing(fn(int $userId, string $checkPermission): bool => in_array($checkPermission, $permissions, true));
    app()->instance(UserPermissions::class, $mock);
}

/** @return array{User, Variant} */
function variantPolicyTestElements(): array
{
    $productType = new ProductType();
    $productType->id = 1;
    $productType->uid = 'randomuid';

    $productTypes = Mockery::mock(ProductTypes::class)->makePartial();
    $productTypes->shouldReceive('getProductTypeById')->andReturn($productType);
    app()->instance(ProductTypes::class, $productTypes);

    $user = new User();
    $user->id = 1;
    $user->admin = false;

    $product = new Product();
    $product->id = 100;
    $product->typeId = $productType->id;

    $variant = new Variant();
    $variant->id = 200;
    $variant->setOwner($product);

    return [$user, $variant];
}

test('variants are authorized by the variant policy', function() {
    expect(Gate::getPolicyFor(Variant::class))->toBeInstanceOf(VariantPolicy::class);
});

test('view authorization follows the owning product', function() {
    [$user, $variant] = variantPolicyTestElements();

    grantVariantPolicyTestPermissions([]);
    expect($variant->canView($user))->toBeFalse();

    grantVariantPolicyTestPermissions(['commerce-viewProductType:randomuid']);
    expect($variant->canView($user))->toBeTrue();
});

test('save delete and duplicate authorization follow saving the owning product', function() {
    [$user, $variant] = variantPolicyTestElements();

    grantVariantPolicyTestPermissions(['commerce-viewProductType:randomuid']);
    expect($variant->canSave($user))->toBeFalse()
        ->and($variant->canDelete($user))->toBeFalse()
        ->and($variant->canDuplicate($user))->toBeFalse();

    grantVariantPolicyTestPermissions(['commerce-saveProductType:randomuid']);
    expect($variant->canSave($user))->toBeTrue()
        ->and($variant->canDelete($user))->toBeTrue()
        ->and($variant->canDuplicate($user))->toBeTrue();
});

test('copy authorization preserves the existing behavior', function() {
    [$user, $variant] = variantPolicyTestElements();
    grantVariantPolicyTestPermissions([]);

    expect($variant->canCopy($user))->toBeTrue();
});

test('variants without an owner cannot be viewed saved deleted or duplicated', function() {
    $user = new User();
    $user->id = 1;
    $user->admin = false;
    $variant = new Variant();
    grantVariantPolicyTestPermissions([]);

    expect($variant->canView($user))->toBeFalse()
        ->and($variant->canSave($user))->toBeFalse()
        ->and($variant->canDelete($user))->toBeFalse()
        ->and($variant->canDuplicate($user))->toBeFalse();
});
