<?php

declare(strict_types=1);

use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Cms\User\UserPermissions;
use CraftCms\Commerce\Product\Elements\Product;
use CraftCms\Commerce\Product\ProductType\Data\ProductType;
use CraftCms\Commerce\Product\ProductType\ProductTypes;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use CraftCms\Commerce\Product\Variant\Policies\VariantPolicy;
use CraftCms\Commerce\Tests\Support\OrdersFixture;
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
    // Enabled products need a persisted variant to be deletable, which these mock-based tests do not have
    $product->enabled = false;

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

test('variant draft and site-specific abilities match product propagation', function() {
    [$user, $variant] = variantPolicyTestElements();
    grantVariantPolicyTestPermissions(['commerce-saveProductType:randomuid']);

    expect($user->can('createDrafts', $variant))->toBeTrue()
        ->and($user->can('duplicateAsDraft', $variant))->toBeTrue()
        ->and($user->can('deleteForSite', $variant))->toBeFalse();
});

test('variants cannot be duplicated after the product type variant limit is reached', function() {
    [$user, $variant] = variantPolicyTestElements();
    grantVariantPolicyTestPermissions(['commerce-saveProductType:randomuid']);

    $variant->getOwner()->getType()->maxVariants = 1;
    $variant->getOwner()->setVariants([$variant]);

    expect($variant->canDuplicate($user))->toBeFalse()
        ->and($user->can('duplicateAsDraft', $variant))->toBeFalse();
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

test('the last variant of an enabled product cannot be deleted', function() {
    $fixture = OrdersFixture::seed();
    $admin = new User();
    $admin->id = 1;
    $admin->admin = true;

    $white = Variant::find()->id($fixture->white->id)->one();
    $blue = Variant::find()->id($fixture->blue->id)->one();

    // Products with other variants can lose one
    expect($admin->can('delete', $white))->toBeTrue();

    // Variants deleted one by one share an owner whose variants were loaded before any were deleted
    expect(Elements::deleteElement($white))->toBeTrue();
    expect($admin->can('delete', $blue))->toBeFalse();

    // The trashed variant can still be deleted permanently
    $trashed = Variant::find()->id($white->id)->trashed()->status(null)->one();
    expect($trashed)->not->toBeNull();
    expect($admin->can('delete', $trashed))->toBeTrue();
});

test('variants of a disabled product can all be deleted', function() {
    $fixture = OrdersFixture::seed();
    $admin = new User();
    $admin->id = 1;
    $admin->admin = true;

    $product = $fixture->product;
    $product->enabled = false;
    expect(Elements::saveElement($product))->toBeTrue();

    expect(Elements::deleteElement(Variant::find()->id($fixture->white->id)->status(null)->one()))->toBeTrue();
    expect($admin->can('delete', Variant::find()->id($fixture->blue->id)->status(null)->one()))->toBeTrue();
});
