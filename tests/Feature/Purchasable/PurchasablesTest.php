<?php

declare(strict_types=1);

use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\I18N;
use CraftCms\Commerce\Helpers\Purchasable as PurchasableHelper;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use CraftCms\Commerce\Purchasable\Purchasables;
use CraftCms\Commerce\Tests\Support\OrdersFixture;

test('null SKUs use hidden temporary values', function() {
    $variant = new Variant(['sku' => null]);

    expect(PurchasableHelper::isTempSku($variant->getSku()))->toBeTrue()
        ->and($variant->getSkuAsText())->toBe('');
});

test('localized dimensions and money request values round trip without converting a blank promotional price to zero', function() {
    $variant = OrdersFixture::seed()->white;

    I18N::withLocale('de', 'de', function() use ($variant) {
        $variant->setAttributesFromRequest([
            'length' => '1,5',
            'width' => '2,75',
            'height' => '3,25',
            'weight' => '4,5',
            'basePrice' => ['value' => '12,50', 'currency' => 'USD', 'locale' => 'de'],
            'basePromotionalPrice' => ['value' => '', 'currency' => 'USD', 'locale' => 'de'],
        ]);
    });

    expect($variant->length)->toBe(1.5)
        ->and($variant->width)->toBe(2.75)
        ->and($variant->height)->toBe(3.25)
        ->and($variant->weight)->toBe(4.5)
        ->and($variant->basePrice)->toBe(12.5)
        ->and($variant->basePromotionalPrice)->toBeNull();
});

test('getPurchasableById does not return a stale instance after the purchasable is re-saved', function() {
    $fixture = OrdersFixture::seed();

    $cached = app(Purchasables::class)->getPurchasableById($fixture->white->id);
    expect($cached->maxQty)->toBeNull();

    $fixture->white->maxQty = 3;
    expect(Elements::saveElement($fixture->white))->toBeTrue();

    $refetched = app(Purchasables::class)->getPurchasableById($fixture->white->id);

    expect($refetched->maxQty)->toBe(3);
});
