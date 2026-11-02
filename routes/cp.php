<?php

use CraftCms\Cms\Http\Controllers\Elements\EditElementController;
use CraftCms\Cms\Http\Controllers\Elements\ElementRevisionsController;
use CraftCms\Cms\Http\Middleware\RequireAdmin;
use CraftCms\Commerce\Http\Controllers\DonationsController;
use CraftCms\Commerce\Http\Controllers\StoreManagement\CatalogPricingController;
use CraftCms\Commerce\Http\Controllers\StoreManagement\CatalogPricingRulesController;
use CraftCms\Commerce\Http\Controllers\StoreManagement\DiscountsController;
use CraftCms\Commerce\Http\Controllers\Settings\EmailsController;
use CraftCms\Commerce\Http\Controllers\Settings\GatewaysController;
use CraftCms\Commerce\Http\Controllers\IndexController;
use CraftCms\Commerce\Http\Controllers\InventoryController;
use CraftCms\Commerce\Http\Controllers\InventoryLocationsController;
use CraftCms\Commerce\Http\Controllers\OrdersController;
use CraftCms\Commerce\Http\Controllers\Settings\GeneralSettingsController;
use CraftCms\Commerce\Http\Controllers\Settings\LineItemStatusesController;
use CraftCms\Commerce\Http\Controllers\Settings\OrderSettingsController;
use CraftCms\Commerce\Http\Controllers\Settings\OrderStatusesController;
use CraftCms\Commerce\Http\Controllers\StoreManagement\PaymentCurrenciesController;
use CraftCms\Commerce\Http\Controllers\Settings\PdfsController;
use CraftCms\Commerce\Http\Controllers\CreateProductController;
use CraftCms\Commerce\Http\Controllers\ProductsController;
use CraftCms\Commerce\Http\Controllers\Settings\ProductTypesController;
use CraftCms\Commerce\Http\Controllers\StoreManagement\SalesController;
use CraftCms\Commerce\Http\Controllers\StoreManagement\ShippingCategoriesController;
use CraftCms\Commerce\Http\Controllers\StoreManagement\ShippingMethodsController;
use CraftCms\Commerce\Http\Controllers\StoreManagement\ShippingRulesController;
use CraftCms\Commerce\Http\Controllers\StoreManagement\ShippingZonesController;
use CraftCms\Commerce\Http\Controllers\StoreManagement\StoreManagementController;
use CraftCms\Commerce\Http\Controllers\Settings\StoresController;
use CraftCms\Commerce\Http\Controllers\StoreManagement\TaxCategoriesController;
use CraftCms\Commerce\Http\Controllers\StoreManagement\TaxRatesController;
use CraftCms\Commerce\Http\Controllers\StoreManagement\TaxZonesController;
use CraftCms\Commerce\Http\Controllers\Settings\TransferSettingsController;
use CraftCms\Commerce\Http\Controllers\VariantsController;
use CraftCms\Commerce\Http\Controllers\TransfersController;
use CraftCms\Commerce\Http\Controllers\Users\UsersController;
use Illuminate\Support\Facades\Route;

use function CraftCms\Cms\cp_url;

Route::middleware(['auth', 'can:accessPlugin-commerce'])->group(function () {
    Route::get('commerce', IndexController::class);

    Route::middleware('can:commerce-manageDonationSettings')
        ->get('commerce/donations', [DonationsController::class, 'edit']);

    Route::middleware(RequireAdmin::class)->group(function () {
        Route::get('commerce/settings', fn() => redirect(cp_url('commerce/settings/general')));

        Route::get('commerce/settings/gateways', [GatewaysController::class, 'index']);
        Route::get('commerce/settings/gateways/new', [GatewaysController::class, 'create']);
        Route::post('commerce/settings/gateways/render-ui', [GatewaysController::class, 'renderUi']);
        Route::get('commerce/settings/gateways/{id}', [GatewaysController::class, 'edit'])->whereNumber('id');

        Route::get('commerce/settings/general', [GeneralSettingsController::class, 'edit']);
        Route::get('commerce/settings/orders', fn() => redirect(cp_url('commerce/settings/orders/fields')));
        Route::get('commerce/settings/orders/fields', [OrderSettingsController::class, 'edit']);
        Route::get('commerce/settings/transfers', [TransferSettingsController::class, 'edit']);

        Route::get('commerce/settings/stores', [StoresController::class, 'index']);
        Route::get('commerce/settings/stores/new', [StoresController::class, 'create']);
        Route::get('commerce/settings/stores/{storeId}', [StoresController::class, 'edit'])->whereNumber('storeId');
        Route::get('commerce/settings/stores/sites', [StoresController::class, 'editSiteStores']);

        Route::get('commerce/settings/orders/statuses', [OrderStatusesController::class, 'index']);
        Route::get('commerce/settings/orders/statuses/{storeHandle}/new', [OrderStatusesController::class, 'create']);
        Route::get('commerce/settings/orders/statuses/{storeHandle}/{id}', [OrderStatusesController::class, 'edit'])->whereNumber('id');

        Route::get('commerce/settings/orders/line-item-statuses', [LineItemStatusesController::class, 'index']);
        Route::get('commerce/settings/orders/line-item-statuses/{storeHandle}/new', [LineItemStatusesController::class, 'create']);
        Route::get('commerce/settings/orders/line-item-statuses/{storeHandle}/{id}', [LineItemStatusesController::class, 'edit'])->whereNumber('id');

        Route::get('commerce/settings/producttypes', [ProductTypesController::class, 'index']);
        Route::get('commerce/settings/producttypes/new', [ProductTypesController::class, 'create']);
        Route::post('commerce/settings/producttypes/render-ui', [ProductTypesController::class, 'renderUi']);
        Route::get('commerce/settings/producttypes/{productTypeId}', [ProductTypesController::class, 'edit'])->whereNumber('productTypeId');

        Route::get('commerce/settings/emails/{storeHandle?}', [EmailsController::class, 'index']);
        Route::get('commerce/settings/emails/{storeHandle}/new', [EmailsController::class, 'create']);
        Route::post('commerce/settings/emails/render-ui', [EmailsController::class, 'renderUi']);
        Route::get('commerce/settings/emails/{storeHandle}/{id}', [EmailsController::class, 'edit'])->whereNumber('id');

        Route::get('commerce/settings/pdfs/{storeHandle?}', [PdfsController::class, 'index']);
        Route::get('commerce/settings/pdfs/{storeHandle}/new', [PdfsController::class, 'create']);
        Route::get('commerce/settings/pdfs/{storeHandle}/{id}', [PdfsController::class, 'edit'])->whereNumber('id');
    });

    // No extra permission middleware: CreateProductController authorizes the new product through ProductPolicy, and
    // ProductsController checks for a viewable product type itself.
    Route::get('commerce/products/{productType}/new', CreateProductController::class);

    // Product/transfer edit screens just resolve the element by {id} — the
    // productTypeHandle segment is cosmetic (matches legacy: the old `elements/edit` route
    // never checked it against the element either). No extra permission middleware here,
    // matching the legacy UrlManager rules — access is enforced by the element's policy
    // (ProductPolicy) or its own canView()/canSave() (Transfer::canView()).
    $idSlugParams = [
        'id' => '\d+',
        'slug' => '(?:-[^\/]*)',
    ];
    Route::get('commerce/products/{productTypeHandle}/{id}{slug}/revisions', [ElementRevisionsController::class, 'index'])->where($idSlugParams);
    Route::get('commerce/products/{productTypeHandle}/{id}{slug?}', EditElementController::class)->where($idSlugParams);
    Route::get('commerce/variants/{id}{slug?}', EditElementController::class)->where($idSlugParams);
    Route::get('commerce/inventory/transfers/{id}{slug?}', EditElementController::class)->where($idSlugParams);

    Route::get('commerce/products/{productTypeHandle?}', [ProductsController::class, 'index']);
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
                Route::get('shippingzones/new', [ShippingZonesController::class, 'create']);
                Route::get('shippingzones/{id}', [ShippingZonesController::class, 'edit'])->whereNumber('id');

                Route::get('shippingcategories', [ShippingCategoriesController::class, 'index']);
                Route::get('shippingcategories/new', [ShippingCategoriesController::class, 'create']);
                Route::get('shippingcategories/{id}', [ShippingCategoriesController::class, 'edit'])->whereNumber('id');

                Route::get('shippingmethods', [ShippingMethodsController::class, 'index']);
                Route::get('shippingmethods/new', [ShippingMethodsController::class, 'create']);
                Route::get('shippingmethods/{id}', [ShippingMethodsController::class, 'edit'])->whereNumber('id');
                Route::get('shippingmethods/{methodId}/shippingrules/new', [ShippingRulesController::class, 'create'])->whereNumber('methodId');
                Route::get('shippingmethods/{methodId}/shippingrules/{ruleId}', [ShippingRulesController::class, 'edit'])->whereNumber(['methodId', 'ruleId']);
            });

        Route::middleware('can:commerce-manageTaxes')
            ->prefix('commerce/store-management/{storeHandle}')
            ->group(function () {
                Route::get('taxcategories', [TaxCategoriesController::class, 'index']);
                Route::get('taxcategories/new', [TaxCategoriesController::class, 'create']);
                Route::get('taxcategories/{id}', [TaxCategoriesController::class, 'edit'])->whereNumber('id');

                Route::get('taxzones', [TaxZonesController::class, 'index']);
                Route::get('taxzones/new', [TaxZonesController::class, 'create']);
                Route::get('taxzones/{id}', [TaxZonesController::class, 'edit'])->whereNumber('id');

                Route::get('taxrates', [TaxRatesController::class, 'index']);
                Route::get('taxrates/new', [TaxRatesController::class, 'create']);
                Route::get('taxrates/{id}', [TaxRatesController::class, 'edit'])->whereNumber('id');
            });

        Route::middleware('can:commerce-managePromotions')->group(function () {
            Route::get('commerce/catalog-pricing', [CatalogPricingController::class, 'index']);

            Route::prefix('commerce/store-management/{storeHandle}')->group(function () {
                Route::get('sales', [SalesController::class, 'index']);
                Route::get('sales/new', [SalesController::class, 'create']);
                Route::get('sales/{id}', [SalesController::class, 'edit'])->whereNumber('id');

                Route::get('discounts', [DiscountsController::class, 'index']);
                Route::get('discounts/new', [DiscountsController::class, 'create']);
                Route::get('discounts/{id}', [DiscountsController::class, 'edit'])->whereNumber('id');

                Route::get('pricing-rules', [CatalogPricingRulesController::class, 'index']);
                Route::get('pricing-rules/new', [CatalogPricingRulesController::class, 'create']);
                Route::get('pricing-rules/{id}', [CatalogPricingRulesController::class, 'edit'])->whereNumber('id');
            });
        });

        Route::middleware('can:commerce-managePaymentCurrencies')
            ->prefix('commerce/store-management/{storeHandle}')
            ->group(function () {
                Route::get('payment-currencies', [PaymentCurrenciesController::class, 'index']);
                Route::get('payment-currencies/new', [PaymentCurrenciesController::class, 'create']);
                Route::get('payment-currencies/{id}', [PaymentCurrenciesController::class, 'edit'])->whereNumber('id');
            });
    });

    // PromotionsController extends BaseCpController (not BaseStoreManagementController) — it
    // only ever needed accessPlugin-commerce, not commerce-manageStoreSettings.
    Route::get('commerce/promotions', fn() => redirect('commerce/promotions/sales'));

    // OrdersController extends the plain Yii2 Controller (not BaseCpController) — its init()
    // only ever checked commerce-manageOrders, not accessPlugin-commerce.
    Route::middleware('can:commerce-manageOrders')->group(function () {
        Route::get('commerce/orders/{orderId}', [OrdersController::class, 'edit'])->whereNumber('orderId');
        Route::get('commerce/orders/{storeHandle}/create', [OrdersController::class, 'create']);
        Route::get('commerce/orders/{orderStatusHandle?}', [OrdersController::class, 'index']);
    });

    // InventoryController checks commerce-manageInventoryStockLevels inline on every action
    // (not via init()) — replicated here as a route-group-wide permission instead.
    Route::middleware('can:commerce-manageInventoryStockLevels')->group(function () {
        Route::get('commerce/inventory/item/{inventoryItemId}', [InventoryController::class, 'editItem'])->whereNumber('inventoryItemId');
        Route::get('commerce/inventory/levels/{inventoryLocationHandle}/orders', [InventoryController::class, 'unfulfilledOrders']);
        Route::get('commerce/inventory/levels/{inventoryLocationHandle}/reserved-orders', [InventoryController::class, 'reservedOrders']);
        Route::get('commerce/inventory/levels/{inventoryLocationHandle}', [InventoryController::class, 'index']);
        Route::get('commerce/inventory/levels', [InventoryController::class, 'index']);
        Route::get('commerce/inventory', [InventoryController::class, 'index']);
    });

    Route::middleware('can:commerce-manageInventoryLocations')->group(function () {
        Route::get('commerce/inventory-locations', [InventoryLocationsController::class, 'index']);
        Route::get('commerce/inventory-locations/new', [InventoryLocationsController::class, 'create']);
        Route::get('commerce/inventory-locations/{inventoryLocationId}', [InventoryLocationsController::class, 'edit'])->whereNumber('inventoryLocationId');
    });

    Route::middleware('can:commerce-manageInventoryTransfers')
        ->get('commerce/inventory/transfers', [TransfersController::class, 'index']);
});

// The Commerce screen on the Edit User screen — permission is enforced inline via the
// EditUserScreensResolving listener in src/Plugin.php (only shows the tab/registers the
// screen if the viewer can access Commerce), matching every other Edit User screen's `auth`-only
// route-level requirement.
Route::middleware('auth')->group(function () {
    Route::get('myaccount/commerce', [UsersController::class, 'index']);
    Route::get('users/{userId}/commerce', [UsersController::class, 'index'])->whereNumber('userId');
});
