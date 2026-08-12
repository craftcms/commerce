<?php

use CraftCms\Cms\Http\Middleware\RequireAdmin;
use CraftCms\Commerce\Http\Controllers\DonationsController;
use CraftCms\Commerce\Http\Controllers\Settings\CatalogPricingController;
use CraftCms\Commerce\Http\Controllers\Settings\CatalogPricingRulesController;
use CraftCms\Commerce\Http\Controllers\Settings\DiscountsController;
use CraftCms\Commerce\Http\Controllers\Settings\GatewaysController;
use CraftCms\Commerce\Http\Controllers\Settings\SalesController;
use CraftCms\Commerce\Http\Controllers\Settings\ShippingCategoriesController;
use CraftCms\Commerce\Http\Controllers\Settings\ShippingMethodsController;
use CraftCms\Commerce\Http\Controllers\Settings\ShippingRulesController;
use CraftCms\Commerce\Http\Controllers\Settings\ShippingZonesController;
use CraftCms\Commerce\Http\Controllers\Settings\TaxCategoriesController;
use CraftCms\Commerce\Http\Controllers\Settings\TaxRatesController;
use CraftCms\Commerce\Http\Controllers\Settings\TaxZonesController;
use CraftCms\Commerce\Http\Controllers\WebhooksController;
use Illuminate\Support\Facades\Route;

Route::post('webhooks/process-webhook', [WebhooksController::class, 'processWebhook']);

// These are also reachable, unauthenticated, at their site-side action URL (per
// CraftCms\Cms\Plugin\Concerns\HasRoutes::registerActionRoutes()) — the `auth`/`can`
// middleware below is what actually protects them, not the URL prefix.
Route::middleware(['auth', 'can:accessPlugin-commerce', 'can:commerce-manageDonationSettings'])
    ->post('donations/save', [DonationsController::class, 'save']);

Route::middleware(['auth', 'can:accessPlugin-commerce', RequireAdmin::class])->group(function () {
    Route::post('gateways/save', [GatewaysController::class, 'save']);
    Route::post('gateways/archive', [GatewaysController::class, 'archive']);
    Route::post('gateways/reorder', [GatewaysController::class, 'reorder']);
});

Route::middleware(['auth', 'can:accessPlugin-commerce', 'can:commerce-manageShipping'])->group(function () {
    Route::post('shipping-zones/save', [ShippingZonesController::class, 'save']);
    Route::post('shipping-zones/delete', [ShippingZonesController::class, 'delete']);
    Route::post('shipping-zones/test-zip', [ShippingZonesController::class, 'testZip']);

    Route::post('shipping-methods/save', [ShippingMethodsController::class, 'save']);
    Route::post('shipping-methods/delete', [ShippingMethodsController::class, 'delete']);
    Route::post('shipping-methods/update-status', [ShippingMethodsController::class, 'updateStatus']);

    Route::post('shipping-rules/save', [ShippingRulesController::class, 'save']);
    Route::post('shipping-rules/duplicate', [ShippingRulesController::class, 'duplicate']);
    Route::post('shipping-rules/reorder', [ShippingRulesController::class, 'reorder']);
    Route::post('shipping-rules/delete', [ShippingRulesController::class, 'delete']);

    Route::post('shipping-categories/save', [ShippingCategoriesController::class, 'save']);
    Route::post('shipping-categories/delete', [ShippingCategoriesController::class, 'delete']);
    Route::post('shipping-categories/set-default-category', [ShippingCategoriesController::class, 'setDefaultCategory']);
});

Route::middleware(['auth', 'can:accessPlugin-commerce', 'can:commerce-manageTaxes'])->group(function () {
    Route::post('tax-zones/save', [TaxZonesController::class, 'save']);
    Route::post('tax-zones/delete', [TaxZonesController::class, 'delete']);
    Route::post('tax-zones/test-zip', [TaxZonesController::class, 'testZip']);

    Route::post('tax-categories/save', [TaxCategoriesController::class, 'save']);
    Route::post('tax-categories/delete', [TaxCategoriesController::class, 'delete']);
    Route::post('tax-categories/set-default-category', [TaxCategoriesController::class, 'setDefaultCategory']);

    Route::post('tax-rates/save', [TaxRatesController::class, 'save']);
    Route::post('tax-rates/delete', [TaxRatesController::class, 'delete']);
    Route::post('tax-rates/update-status', [TaxRatesController::class, 'updateStatus']);
});

Route::middleware(['auth', 'can:accessPlugin-commerce', 'can:commerce-managePromotions'])->group(function () {
    Route::post('sales/save', [SalesController::class, 'save']);
    Route::post('sales/reorder', [SalesController::class, 'reorder']);
    Route::post('sales/delete', [SalesController::class, 'delete']);
    Route::match(['get', 'post'], 'sales/get-all-sales', [SalesController::class, 'getAllSales']);
    Route::post('sales/get-sales-by-product-id', [SalesController::class, 'getSalesByProductId']);
    Route::post('sales/get-sales-by-purchasable-id', [SalesController::class, 'getSalesByPurchasableId']);
    Route::post('sales/add-purchasable-to-sale', [SalesController::class, 'addPurchasableToSale']);
    Route::post('sales/update-status', [SalesController::class, 'updateStatus']);

    Route::match(['get', 'post'], 'discounts/table-data', [DiscountsController::class, 'tableData']);
    Route::post('discounts/save', [DiscountsController::class, 'save']);
    Route::post('discounts/reorder', [DiscountsController::class, 'reorder']);
    Route::post('discounts/move-to-page', [DiscountsController::class, 'moveToPage']);
    Route::post('discounts/delete', [DiscountsController::class, 'delete']);
    Route::post('discounts/clear-discount-uses', [DiscountsController::class, 'clearDiscountUses']);
    Route::post('discounts/update-status', [DiscountsController::class, 'updateStatus']);
    Route::post('discounts/get-discounts-by-purchasable-id', [DiscountsController::class, 'getDiscountsByPurchasableId']);
    Route::post('discounts/generate-coupons', [DiscountsController::class, 'generateCoupons']);

    Route::post('catalog-pricing-rules/save', [CatalogPricingRulesController::class, 'save']);
    Route::post('catalog-pricing-rules/delete', [CatalogPricingRulesController::class, 'delete']);
    Route::post('catalog-pricing-rules/update-status', [CatalogPricingRulesController::class, 'updateStatus']);

    Route::post('catalog-pricing/filter', [CatalogPricingController::class, 'filter']);
    Route::post('catalog-pricing/prices', [CatalogPricingController::class, 'prices']);
    Route::get('catalog-pricing/queue-status', [CatalogPricingController::class, 'queueStatus']);
    Route::post('catalog-pricing/get-catalog-prices', [CatalogPricingController::class, 'getCatalogPrices']);
});
