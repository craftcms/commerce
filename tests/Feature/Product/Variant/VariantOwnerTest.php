<?php

declare(strict_types=1);

use CraftCms\Commerce\Product\Elements\Product;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use CraftCms\Commerce\Tests\Support\VariantQueryFixture;

beforeEach(function() {
    $this->fixture = VariantQueryFixture::seed();
});

test('ownerType resolves to Product', function() {
    $method = new ReflectionMethod(Variant::class, 'ownerType');

    expect($method->invoke(new Variant()))->toBe(Product::class);
});

test('product is included in extraFields', function() {
    expect((new Variant())->extraFields())->toContain('product');
});

test('getOwner returns the owning product', function() {
    $owner = $this->fixture->whiteVariant->getOwner();

    expect($owner)->toBeInstanceOf(Product::class);
    expect($owner->id)->toBe($this->fixture->tee->id);
});

test('getPrimaryOwner returns the owning product', function() {
    $primaryOwner = $this->fixture->whiteVariant->getPrimaryOwner();

    expect($primaryOwner)->toBeInstanceOf(Product::class);
    expect($primaryOwner->id)->toBe($this->fixture->tee->id);
});

test('getProduct still works as an alias for getOwner', function() {
    $product = $this->fixture->hoodieVariant->getProduct();

    expect($product)->toBeInstanceOf(Product::class);
    expect($product->id)->toBe($this->fixture->hoodie->id);
});

test('setOwner accepts a Product instance', function() {
    $variant = new Variant();
    $variant->setOwner($this->fixture->tee);

    $owner = $variant->getOwner();
    expect($owner)->toBeInstanceOf(Product::class);
    expect($owner->id)->toBe($this->fixture->tee->id);
});

test('a variant\'s owner resolves to the same site as the variant itself', function() {
    $owner = $this->fixture->whiteVariant->getOwner();

    expect($owner)->toBeInstanceOf(Product::class);
    expect($owner->id)->toBe($this->fixture->tee->id);
    expect($owner->siteId)->toBe($this->fixture->whiteVariant->siteId);
});
