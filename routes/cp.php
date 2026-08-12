<?php

use CraftCms\Cms\Http\Middleware\RequireAdmin;
use CraftCms\Commerce\Http\Controllers\DonationsController;
use CraftCms\Commerce\Http\Controllers\Settings\GatewaysController;
use CraftCms\Commerce\Http\Controllers\Settings\ShippingCategoriesController;
use CraftCms\Commerce\Http\Controllers\Settings\ShippingMethodsController;
use CraftCms\Commerce\Http\Controllers\Settings\ShippingRulesController;
use CraftCms\Commerce\Http\Controllers\Settings\ShippingZonesController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:accessPlugin-commerce'])->group(function () {
    Route::middleware('can:commerce-manageDonationSettings')
        ->get('commerce/donations', [DonationsController::class, 'edit']);

    Route::middleware(RequireAdmin::class)->group(function () {
        Route::get('commerce/settings/gateways', [GatewaysController::class, 'index']);
        Route::get('commerce/settings/gateways/new', [GatewaysController::class, 'edit']);
        Route::get('commerce/settings/gateways/{id}', [GatewaysController::class, 'edit'])->whereNumber('id');
    });

    Route::middleware('can:commerce-manageShipping')
        ->prefix('commerce/store-management/{storeHandle}')
        ->group(function () {
            Route::get('shippingzones', [ShippingZonesController::class, 'index']);
            Route::get('shippingzones/new', [ShippingZonesController::class, 'edit']);
            Route::get('shippingzones/{id}', [ShippingZonesController::class, 'edit'])->whereNumber('id');

            Route::get('shippingcategories', [ShippingCategoriesController::class, 'index']);
            Route::get('shippingcategories/new', [ShippingCategoriesController::class, 'edit']);
            Route::get('shippingcategories/{id}', [ShippingCategoriesController::class, 'edit'])->whereNumber('id');

            Route::get('shippingmethods', [ShippingMethodsController::class, 'index']);
            Route::get('shippingmethods/new', [ShippingMethodsController::class, 'edit']);
            Route::get('shippingmethods/{id}', [ShippingMethodsController::class, 'edit'])->whereNumber('id');
            Route::get('shippingmethods/{methodId}/shippingrules/new', [ShippingRulesController::class, 'edit'])->whereNumber('methodId');
            Route::get('shippingmethods/{methodId}/shippingrules/{ruleId}', [ShippingRulesController::class, 'edit'])->whereNumber(['methodId', 'ruleId']);
        });
});
