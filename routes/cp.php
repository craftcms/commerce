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

    Route::middleware('can:commerce-manageTaxes')
        ->prefix('commerce/store-management/{storeHandle}')
        ->group(function () {
            Route::get('taxcategories', [TaxCategoriesController::class, 'index']);
            Route::get('taxcategories/new', [TaxCategoriesController::class, 'edit']);
            Route::get('taxcategories/{id}', [TaxCategoriesController::class, 'edit'])->whereNumber('id');

            Route::get('taxzones', [TaxZonesController::class, 'index']);
            Route::get('taxzones/new', [TaxZonesController::class, 'edit']);
            Route::get('taxzones/{id}', [TaxZonesController::class, 'edit'])->whereNumber('id');

            Route::get('taxrates', [TaxRatesController::class, 'index']);
            Route::get('taxrates/new', [TaxRatesController::class, 'edit']);
            Route::get('taxrates/{id}', [TaxRatesController::class, 'edit'])->whereNumber('id');
        });

    Route::middleware('can:commerce-managePromotions')->group(function () {
        Route::get('commerce/promotions', fn() => redirect('commerce/promotions/sales'));
        Route::get('commerce/catalog-pricing', [CatalogPricingController::class, 'index']);

        Route::prefix('commerce/store-management/{storeHandle}')->group(function () {
            Route::get('sales', [SalesController::class, 'index']);
            Route::get('sales/new', [SalesController::class, 'edit']);
            Route::get('sales/{id}', [SalesController::class, 'edit'])->whereNumber('id');

            Route::get('discounts', [DiscountsController::class, 'index']);
            Route::get('discounts/new', [DiscountsController::class, 'edit']);
            Route::get('discounts/{id}', [DiscountsController::class, 'edit'])->whereNumber('id');

            Route::get('pricing-rules', [CatalogPricingRulesController::class, 'index']);
            Route::get('pricing-rules/new', [CatalogPricingRulesController::class, 'edit']);
            Route::get('pricing-rules/{id}', [CatalogPricingRulesController::class, 'edit'])->whereNumber('id');
        });
    });
});
