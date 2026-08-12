<?php

use CraftCms\Cms\Http\Middleware\RequireAdmin;
use CraftCms\Commerce\Http\Controllers\DonationsController;
use CraftCms\Commerce\Http\Controllers\Settings\CatalogPricingController;
use CraftCms\Commerce\Http\Controllers\Settings\CatalogPricingRulesController;
use CraftCms\Commerce\Http\Controllers\Settings\DiscountsController;
use CraftCms\Commerce\Http\Controllers\Settings\GatewaysController;
use CraftCms\Commerce\Http\Controllers\InventoryController;
use CraftCms\Commerce\Http\Controllers\InventoryLocationsController;
use CraftCms\Commerce\Http\Controllers\OrdersController;
use CraftCms\Commerce\Http\Controllers\Settings\LineItemStatusesController;
use CraftCms\Commerce\Http\Controllers\Settings\OrderSettingsController;
use CraftCms\Commerce\Http\Controllers\Settings\OrderStatusesController;
use CraftCms\Commerce\Http\Controllers\Settings\PaymentCurrenciesController;
use CraftCms\Commerce\Http\Controllers\Settings\PlansController;
use CraftCms\Commerce\Http\Controllers\ProductsController;
use CraftCms\Commerce\Http\Controllers\Settings\ProductTypesController;
use CraftCms\Commerce\Http\Controllers\Settings\SalesController;
use CraftCms\Commerce\Http\Controllers\Settings\SettingsController;
use CraftCms\Commerce\Http\Controllers\Settings\ShippingCategoriesController;
use CraftCms\Commerce\Http\Controllers\Settings\ShippingMethodsController;
use CraftCms\Commerce\Http\Controllers\Settings\ShippingRulesController;
use CraftCms\Commerce\Http\Controllers\Settings\ShippingZonesController;
use CraftCms\Commerce\Http\Controllers\Settings\StoreManagementController;
use CraftCms\Commerce\Http\Controllers\Settings\StoresController;
use CraftCms\Commerce\Http\Controllers\SubscriptionsController;
use CraftCms\Commerce\Http\Controllers\Settings\TaxCategoriesController;
use CraftCms\Commerce\Http\Controllers\Settings\TaxRatesController;
use CraftCms\Commerce\Http\Controllers\Settings\TaxZonesController;
use CraftCms\Commerce\Http\Controllers\TransfersController;
use CraftCms\Commerce\Http\Controllers\VariantsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:accessPlugin-commerce'])->group(function () {
    Route::middleware('can:commerce-manageDonationSettings')
        ->get('commerce/donations', [DonationsController::class, 'edit']);

    Route::middleware(RequireAdmin::class)->group(function () {
        Route::get('commerce/settings/gateways', [GatewaysController::class, 'index']);
        Route::get('commerce/settings/gateways/new', [GatewaysController::class, 'edit']);
        Route::get('commerce/settings/gateways/{id}', [GatewaysController::class, 'edit'])->whereNumber('id');

        Route::get('commerce/settings/general', [SettingsController::class, 'edit']);
        Route::get('commerce/settings/ordersettings', [OrderSettingsController::class, 'edit']);
        Route::get('commerce/settings/transfers', [SettingsController::class, 'editTransferSettings']);
        Route::get('commerce/settings/subscriptions', [SettingsController::class, 'editSubscriptionSettings']);

        Route::get('commerce/settings/stores', [StoresController::class, 'storesIndex']);
        Route::get('commerce/settings/stores/new', [StoresController::class, 'editStore']);
        Route::get('commerce/settings/stores/{storeId}', [StoresController::class, 'editStore'])->whereNumber('storeId');
        Route::get('commerce/settings/sites', [StoresController::class, 'editSiteStores']);

        Route::get('commerce/settings/orderstatuses', [OrderStatusesController::class, 'index']);
        Route::get('commerce/settings/orderstatuses/{storeHandle}/new', [OrderStatusesController::class, 'edit']);
        Route::get('commerce/settings/orderstatuses/{storeHandle}/{id}', [OrderStatusesController::class, 'edit'])->whereNumber('id');

        Route::get('commerce/settings/lineitemstatuses', [LineItemStatusesController::class, 'index']);
        Route::get('commerce/settings/lineitemstatuses/{storeHandle}/new', [LineItemStatusesController::class, 'edit']);
        Route::get('commerce/settings/lineitemstatuses/{storeHandle}/{id}', [LineItemStatusesController::class, 'edit'])->whereNumber('id');

        Route::get('commerce/settings/producttypes', [ProductTypesController::class, 'productTypeIndex']);
        Route::get('commerce/settings/producttypes/new', [ProductTypesController::class, 'editProductType']);
        Route::get('commerce/settings/producttypes/{productTypeId}', [ProductTypesController::class, 'editProductType'])->whereNumber('productTypeId');
    });

    // ProductsController/VariantsController extend BaseCpController directly (no extra
    // permission beyond accessPlugin-commerce) — each additionally guards its own methods with
    // an inline "does the user have access to any product type" check.
    Route::get('commerce/products/{productType}/new', [ProductsController::class, 'create']);
    Route::get('commerce/products/{productTypeHandle?}', [ProductsController::class, 'productIndex']);
    Route::get('commerce/variants/{productTypeHandle?}', [VariantsController::class, 'index']);

    // BaseStoreManagementController::init() always required commerce-manageStoreSettings, on
    // top of whichever more specific permission each feature area's own controller adds — every
    // route in this group must check both, matching that legacy compound check exactly.
    Route::middleware('can:commerce-manageStoreSettings')->group(function () {
        Route::get('commerce/store-management', [StoreManagementController::class, 'index']);
        Route::get('commerce/store-management/{storeHandle}', [StoreManagementController::class, 'edit']);

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

        Route::prefix('commerce/store-management/{storeHandle}')->group(function () {
            Route::get('payment-currencies', [PaymentCurrenciesController::class, 'index']);
            Route::get('payment-currencies/new', [PaymentCurrenciesController::class, 'edit']);
            Route::get('payment-currencies/{id}', [PaymentCurrenciesController::class, 'edit'])->whereNumber('id');
        });
    });

    // PromotionsController extends BaseCpController (not BaseStoreManagementController) — it
    // only ever needed accessPlugin-commerce, not commerce-manageStoreSettings.
    Route::get('commerce/promotions', fn() => redirect('commerce/promotions/sales'));

    // OrdersController extends the plain Yii2 Controller (not BaseCpController) — its init()
    // only ever checked commerce-manageOrders, not accessPlugin-commerce.
    Route::middleware('can:commerce-manageOrders')->group(function () {
        Route::get('commerce/orders/{orderId}', [OrdersController::class, 'editOrder'])->whereNumber('orderId');
        Route::get('commerce/orders/{storeHandle}/create', [OrdersController::class, 'create']);
        Route::get('commerce/orders/{orderStatusHandle?}', [OrdersController::class, 'orderIndex']);
    });

    // InventoryController checks commerce-manageInventoryStockLevels inline on every action
    // (not via init()) — replicated here as a route-group-wide permission instead.
    Route::middleware('can:commerce-manageInventoryStockLevels')->group(function () {
        Route::get('commerce/inventory/item/{inventoryItemId}', [InventoryController::class, 'itemEdit'])->whereNumber('inventoryItemId');
        Route::get('commerce/inventory/levels/{inventoryLocationHandle}', [InventoryController::class, 'editLocationLevels']);
        Route::get('commerce/inventory/levels', [InventoryController::class, 'editLocationLevels']);
        Route::get('commerce/inventory', [InventoryController::class, 'editLocationLevels']);
    });

    Route::middleware('can:commerce-manageInventoryLocations')->group(function () {
        Route::get('commerce/inventory-locations', [InventoryLocationsController::class, 'index']);
        Route::get('commerce/inventory-locations/new', [InventoryLocationsController::class, 'edit']);
        Route::get('commerce/inventory-locations/{inventoryLocationId}', [InventoryLocationsController::class, 'edit'])->whereNumber('inventoryLocationId');
    });

    Route::middleware('can:commerce-manageInventoryTransfers')
        ->get('commerce/inventory/transfers', [TransfersController::class, 'index']);

    Route::get('commerce/subscription-plans', [PlansController::class, 'planIndex']);
    Route::middleware('can:commerce-manageSubscriptions')->group(function () {
        Route::get('commerce/subscription-plans/new', [PlansController::class, 'editPlan']);
        Route::get('commerce/subscription-plans/{planId}', [PlansController::class, 'editPlan'])->whereNumber('planId');
    });
});

// SubscriptionsController extends the plain Yii2 BaseController — no blanket
// accessPlugin-commerce check at all. index() explicitly requires commerce-manageSubscriptions;
// edit() only checks per-subscription view permission inline (a subscription's own owner can
// reach it even without commerce-manageSubscriptions), so it must not be wrapped in either
// permission — just `auth`.
Route::middleware(['auth', 'can:commerce-manageSubscriptions'])
    ->get('commerce/subscriptions/{plan?}', [SubscriptionsController::class, 'index']);
Route::middleware('auth')
    ->get('commerce/subscriptions/{subscriptionId}', [SubscriptionsController::class, 'edit'])->whereNumber('subscriptionId');
