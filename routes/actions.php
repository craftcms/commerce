<?php

use CraftCms\Cms\Http\Middleware\RequireAdmin;
use CraftCms\Commerce\Http\Controllers\DonationsController;
use CraftCms\Commerce\Http\Controllers\Settings\GatewaysController;
use CraftCms\Commerce\Http\Controllers\Settings\ShippingCategoriesController;
use CraftCms\Commerce\Http\Controllers\Settings\ShippingMethodsController;
use CraftCms\Commerce\Http\Controllers\Settings\ShippingRulesController;
use CraftCms\Commerce\Http\Controllers\Settings\ShippingZonesController;
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
