<?php

declare(strict_types=1);

use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Promotion\Sales;
use CraftCms\Commerce\Tests\Support\SalesFixture;
use Illuminate\Support\Facades\DB;

test('getAllSales returns every sale keyed by ID, with its relation IDs attached', function() {
    $fixture = SalesFixture::seed();
    $sales = app(Sales::class)->getAllSales();

    expect($sales)->toHaveCount(2);

    $firstSale = $sales[$fixture->percentageSale->id] ?? null;
    expect($firstSale)->not->toBeNull();
    expect($firstSale->name)->toBe($fixture->percentageSale->name);

    expect(array_map(intval(...), $firstSale->getPurchasableIds()))->toBe([$fixture->radHood->id]);
    expect($firstSale->getUserGroupIds())->toBe([]);
});

test('getSaleById returns a sale by ID, or null when none matches', function() {
    $fixture = SalesFixture::seed();
    $sales = app(Sales::class);

    $sale = $sales->getSaleById($fixture->percentageSale->id);
    expect($sale->name)->toBe($fixture->percentageSale->name);

    expect($sales->getSaleById(999999))->toBeNull();
});

test('getSalesForPurchasable returns sales matching the purchasable', function() {
    $fixture = SalesFixture::seed();
    $sales = app(Sales::class);

    $sale = $sales->getSaleById($fixture->percentageSale->id);

    expect($sales->getSalesForPurchasable($fixture->radHood))->toBe([$sale]);
});

test('getSalesRelatedToPurchasable returns sales related by purchasable ID', function() {
    $fixture = SalesFixture::seed();
    $sales = app(Sales::class);

    $sale = $sales->getSaleById($fixture->allRelationshipsSale->id);

    expect($sales->getSalesRelatedToPurchasable($fixture->hctWhite))->toBe([$sale]);
});

test('getSalePriceForPurchasable applies the matching sale for the signed-in customer', function() {
    $fixture = SalesFixture::seed();
    $sales = app(Sales::class);

    $this->actingAs($fixture->customer, 'craft');

    $salePrice = $sales->getSalePriceForPurchasable($fixture->radHood);
    expect($salePrice)->not->toBe($fixture->radHood->getPrice());
    expect($salePrice)->toBe(111.59);

    $salePrice = $sales->getSalePriceForPurchasable($fixture->hctWhite);
    expect($salePrice)->not->toBe($fixture->hctWhite->getPrice());
    expect($salePrice)->toBe(15.99);
});

test('saveSale persists changes and bumps dateUpdated', function() {
    $fixture = SalesFixture::seed();
    $sales = app(Sales::class);

    $sale = $sales->getSaleById($fixture->allRelationshipsSale->id);
    $originalName = $sale->name;
    $originalDateUpdated = DB::table(Table::SALES)->where('id', $sale->id)->value('dateUpdated');

    $sale->name = 'CHANGED';

    // Absolutely make sure enough time has passed.
    sleep(1);
    $saveResult = $sales->saveSale($sale);
    $newDateUpdated = DB::table(Table::SALES)->where('id', $sale->id)->value('dateUpdated');

    expect($sale->errors()->isEmpty())->toBeTrue();
    expect($saveResult)->toBeTrue();
    expect($sale->name)->not->toBe($originalName);
    expect($sale->name)->toBe('CHANGED');
    expect($newDateUpdated)->toBeGreaterThan($originalDateUpdated);
});

test('reorderSales updates sortOrder, reflected immediately by getAllSales', function() {
    SalesFixture::seed();
    $sales = app(Sales::class);

    $originalOrder = array_map(intval(...), array_keys($sales->getAllSales()));
    $newOrder = array_reverse($originalOrder);

    expect($sales->reorderSales($newOrder))->toBeTrue();

    $dbOrder = array_map(
        intval(...),
        DB::table(Table::SALES)->orderBy('sortOrder')->pluck('id')->all(),
    );
    expect($dbOrder)->not->toBe($originalOrder);
    expect($dbOrder)->toBe($newOrder);

    // Make sure the order has updated if we retrieve the sales again in the same request.
    $newOrderFromGetSales = array_map(intval(...), array_keys($sales->getAllSales()));
    expect($newOrderFromGetSales)->toBe($dbOrder);
});

test('deleteSaleById removes the sale and clears memoized lookups', function() {
    $fixture = SalesFixture::seed();
    $sales = app(Sales::class);

    // Pre-fetch to exercise memoization before the delete.
    $sales->getAllSales();

    $id = $fixture->percentageSale->id;
    expect($sales->deleteSaleById($id))->toBeTrue();

    expect($sales->getSaleById($id))->toBeNull();
    expect(array_key_exists($id, $sales->getAllSales()))->toBeFalse();
});
