<?php

use CraftCms\Cms\Http\Middleware\RequireAdmin;
use CraftCms\Cms\Http\Middleware\RequireCpRequest;
use CraftCms\Commerce\Http\Controllers\CartController;
use CraftCms\Commerce\Http\Controllers\CreateProductController;
use CraftCms\Commerce\Http\Controllers\DonationsController;
use CraftCms\Commerce\Http\Controllers\OrdersController;
use CraftCms\Commerce\Http\Controllers\StoreManagement\CatalogPricingController;
use CraftCms\Commerce\Http\Controllers\StoreManagement\CatalogPricingRulesController;
use CraftCms\Commerce\Http\Controllers\DownloadsController;
use CraftCms\Commerce\Http\Controllers\EmailPreviewController;
use CraftCms\Commerce\Http\Controllers\StoreManagement\DiscountsController;
use CraftCms\Commerce\Http\Controllers\Settings\EmailsController;
use CraftCms\Commerce\Http\Controllers\FormulasController;
use CraftCms\Commerce\Http\Controllers\Settings\GatewaysController;
use CraftCms\Commerce\Http\Controllers\Settings\GeneralSettingsController;
use CraftCms\Commerce\Http\Controllers\InventoryController;
use CraftCms\Commerce\Http\Controllers\InventoryLocationsController;
use CraftCms\Commerce\Http\Controllers\Settings\LineItemStatusesController;
use CraftCms\Commerce\Http\Controllers\Settings\OrderSettingsController;
use CraftCms\Commerce\Http\Controllers\Settings\OrderStatusesController;
use CraftCms\Commerce\Http\Controllers\StoreManagement\PaymentCurrenciesController;
use CraftCms\Commerce\Http\Controllers\PaymentSourcesController;
use CraftCms\Commerce\Http\Controllers\PaymentsController;
use CraftCms\Commerce\Http\Controllers\Settings\PdfsController;
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
use CraftCms\Commerce\Http\Controllers\TransfersController;
use CraftCms\Commerce\Http\Controllers\UserOrdersController;
use CraftCms\Commerce\Http\Controllers\WebhooksController;
use CraftCms\Commerce\Http\Middleware\LogDeprecatedAction;
use CraftCms\Commerce\Http\RateLimiters\CartChallengeRateLimiter;
use CraftCms\Commerce\Http\RateLimiters\CartRateLimiter;
use CraftCms\Commerce\Http\RateLimiters\PdfChallengeRateLimiter;
use Illuminate\Support\Facades\Route;

Route::post('webhooks/process-webhook', [WebhooksController::class, 'process']);

Route::match(['get', 'post'], 'user-orders/get-orders', [UserOrdersController::class, 'index']);

// Anonymous by design — carts are usable by guests. Rate-limited (not auth-gated) to blunt
// enumeration/brute-force attempts against the number/couponCode params.
Route::middleware('throttle:' . CartRateLimiter::NAME)->group(function () {
    Route::get('cart/show', [CartController::class, 'show']);
    Route::post('cart/update', [CartController::class, 'update']);
    Route::match(['get', 'post'], 'cart/load', [CartController::class, 'load']);
    Route::post('cart/complete', [CartController::class, 'complete']);

    // @deprecated in 6.0.0. Use `commerce/cart/show` instead.
    Route::get('cart/get-cart', [CartController::class, 'show'])
        ->middleware(LogDeprecatedAction::replacedBy('commerce/cart/get-cart', 'commerce/cart/show'));
    // @deprecated in 6.0.0. Use `commerce/cart/update` instead.
    Route::post('cart/update-cart', [CartController::class, 'update'])
        ->middleware(LogDeprecatedAction::replacedBy('commerce/cart/update-cart', 'commerce/cart/update'));
    // @deprecated in 6.0.0. Use `commerce/cart/load` instead.
    Route::match(['get', 'post'], 'cart/load-cart', [CartController::class, 'load'])
        ->middleware(LogDeprecatedAction::replacedBy('commerce/cart/load-cart', 'commerce/cart/load'));
});

Route::post('cart/forget', [CartController::class, 'forget']);
// @deprecated in 6.0.0. Use `commerce/cart/forget` instead.
Route::post('cart/forget-cart', [CartController::class, 'forget'])
    ->middleware(LogDeprecatedAction::replacedBy('commerce/cart/forget-cart', 'commerce/cart/forget'));
Route::get('cart/email-challenge', [CartController::class, 'emailChallenge']);
Route::post('cart/cart-challenge', [CartController::class, 'cartChallenge'])
    ->middleware('throttle:' . CartChallengeRateLimiter::NAME);
Route::get('cart/cart-sent', [CartController::class, 'cartSent']);

// Anonymous by design — guest checkout must be able to pay. Each action gates its own
// order/customer-ownership checks inline (mirrors CartController's own permission model).
Route::post('payments/pay', [PaymentsController::class, 'pay']);
Route::match(['get', 'post'], 'payments/complete-payment', [PaymentsController::class, 'complete']);

Route::post('payment-sources/add', [PaymentSourcesController::class, 'store']);
Route::post('payment-sources/set-primary-payment-source', [PaymentSourcesController::class, 'setPrimary']);
Route::post('payment-sources/delete', [PaymentSourcesController::class, 'destroy']);

// These are also reachable, unauthenticated, at their site-side action URL (per
// CraftCms\Cms\Plugin\Concerns\HasRoutes::registerActionRoutes()) — the `auth`/`can`
// middleware below is what actually protects them, not the URL prefix.
Route::middleware(['auth', 'can:accessPlugin-commerce', 'can:commerce-manageDonationSettings'])->group(function () {
    Route::post('donations/save', [DonationsController::class, 'store']);
    Route::post('donations/render-ui', [DonationsController::class, 'renderUi']);
});

Route::middleware(['auth', 'can:accessPlugin-commerce', RequireAdmin::class])->group(function () {
    Route::post('gateways/save', [GatewaysController::class, 'store']);
    Route::match(['post', 'delete'], 'gateways/archive', [GatewaysController::class, 'archive']);
    Route::post('gateways/reorder', [GatewaysController::class, 'reorder']);

    Route::post('settings/save-settings', [GeneralSettingsController::class, 'store']);
    Route::post('settings/save-transfer-settings', [TransferSettingsController::class, 'store']);
    Route::post('order-settings/save', [OrderSettingsController::class, 'store']);

    Route::post('stores/save-store', [StoresController::class, 'store']);
    Route::match(['post', 'delete'], 'stores/delete-store', [StoresController::class, 'destroy']);
    Route::post('stores/reorder-stores', [StoresController::class, 'reorder']);
    Route::post('stores/save-site-stores', [StoresController::class, 'saveSiteStores']);

    Route::post('order-statuses/save', [OrderStatusesController::class, 'store']);
    Route::match(['get', 'post'], 'order-statuses/get-order-statuses', [OrderStatusesController::class, 'list']);
    Route::post('order-statuses/reorder', [OrderStatusesController::class, 'reorder']);
    Route::match(['post', 'delete'], 'order-statuses/delete', [OrderStatusesController::class, 'destroy']);

    Route::post('line-item-statuses/save', [LineItemStatusesController::class, 'store']);
    Route::post('line-item-statuses/reorder', [LineItemStatusesController::class, 'reorder']);
    Route::match(['post', 'delete'], 'line-item-statuses/archive', [LineItemStatusesController::class, 'archive']);

    Route::post('product-types/save-product-type', [ProductTypesController::class, 'store']);
    Route::match(['post', 'delete'], 'product-types/delete-product-type', [ProductTypesController::class, 'destroy']);
});

// BaseStoreManagementController::init() always required commerce-manageStoreSettings, on top of
// whichever more specific permission each feature area's own controller adds — every route in
// this group must check both, matching that legacy compound check exactly.
Route::middleware(['auth', 'can:accessPlugin-commerce', 'can:commerce-manageStoreSettings'])->group(function () {
    Route::post('store-management/save', [StoreManagementController::class, 'store']);
    Route::post('store-management/render-ui', [StoreManagementController::class, 'renderUi']);

    Route::middleware('can:commerce-managePaymentCurrencies')->group(function () {
        Route::post('payment-currencies/save', [PaymentCurrenciesController::class, 'store']);
        Route::match(['post', 'delete'], 'payment-currencies/delete', [PaymentCurrenciesController::class, 'destroy']);
    });

    Route::middleware('can:commerce-manageShipping')->group(function () {
        Route::post('shipping-zones/save', [ShippingZonesController::class, 'store']);
        Route::match(['post', 'delete'], 'shipping-zones/delete', [ShippingZonesController::class, 'destroy']);
        Route::post('shipping-zones/test-zip', [ShippingZonesController::class, 'testZip']);

        Route::post('shipping-methods/save', [ShippingMethodsController::class, 'store']);
        Route::match(['post', 'delete'], 'shipping-methods/delete', [ShippingMethodsController::class, 'destroy']);
        Route::post('shipping-methods/update-status', [ShippingMethodsController::class, 'updateStatus']);

        Route::post('shipping-rules/save', [ShippingRulesController::class, 'store']);
        Route::post('shipping-rules/duplicate', [ShippingRulesController::class, 'duplicate']);
        Route::post('shipping-rules/reorder', [ShippingRulesController::class, 'reorder']);
        Route::match(['post', 'delete'], 'shipping-rules/delete', [ShippingRulesController::class, 'destroy']);
        Route::post('shipping-rules/render-ui', [ShippingRulesController::class, 'renderUi']);

        Route::post('shipping-categories/save', [ShippingCategoriesController::class, 'store']);
        Route::match(['post', 'delete'], 'shipping-categories/delete', [ShippingCategoriesController::class, 'destroy']);
        Route::post('shipping-categories/set-default-category', [ShippingCategoriesController::class, 'setDefault']);
        Route::post('shipping-categories/render-ui', [ShippingCategoriesController::class, 'renderUi']);
    });

    Route::middleware('can:commerce-manageTaxes')->group(function () {
        Route::post('tax-zones/save', [TaxZonesController::class, 'store']);
        Route::match(['post', 'delete'], 'tax-zones/delete', [TaxZonesController::class, 'destroy']);
        Route::post('tax-zones/test-zip', [TaxZonesController::class, 'testZip']);

        Route::post('tax-categories/save', [TaxCategoriesController::class, 'store']);
        Route::match(['post', 'delete'], 'tax-categories/delete', [TaxCategoriesController::class, 'destroy']);
        Route::post('tax-categories/set-default-category', [TaxCategoriesController::class, 'setDefault']);

        Route::post('tax-rates/save', [TaxRatesController::class, 'store']);
        Route::match(['post', 'delete'], 'tax-rates/delete', [TaxRatesController::class, 'destroy']);
        Route::post('tax-rates/update-status', [TaxRatesController::class, 'updateStatus']);
        Route::post('tax-rates/render-ui', [TaxRatesController::class, 'renderUi']);
    });

    Route::middleware('can:commerce-managePromotions')->group(function () {
        Route::post('sales/save', [SalesController::class, 'store']);
        Route::post('sales/reorder', [SalesController::class, 'reorder']);
        Route::post('sales/delete', [SalesController::class, 'destroy']);
        Route::match(['get', 'post'], 'sales/get-all-sales', [SalesController::class, 'list']);
        Route::post('sales/get-sales-by-product-id', [SalesController::class, 'byProduct']);
        Route::post('sales/get-sales-by-purchasable-id', [SalesController::class, 'byPurchasable']);
        Route::post('sales/add-purchasable-to-sale', [SalesController::class, 'addPurchasable']);
        Route::post('sales/update-status', [SalesController::class, 'updateStatus']);

        Route::post('discounts/save', [DiscountsController::class, 'store']);
        Route::post('discounts/render-ui', [DiscountsController::class, 'renderUi']);
        Route::post('discounts/reorder', [DiscountsController::class, 'reorder']);
        Route::post('discounts/table-data', [DiscountsController::class, 'tableData']);
        Route::post('discounts/move-to-page', [DiscountsController::class, 'moveToPage']);
        Route::match(['post', 'delete'], 'discounts/delete', [DiscountsController::class, 'destroy']);
        Route::post('discounts/clear-discount-uses', [DiscountsController::class, 'clearUses']);
        Route::post('discounts/update-status', [DiscountsController::class, 'updateStatus']);
        Route::post('discounts/get-discounts-by-purchasable-id', [DiscountsController::class, 'byPurchasable']);
        Route::post('discounts/generate-coupons', [DiscountsController::class, 'generateCoupons']);

        Route::post('catalog-pricing-rules/save', [CatalogPricingRulesController::class, 'store']);
        Route::post('catalog-pricing-rules/render-ui', [CatalogPricingRulesController::class, 'renderUi']);
        Route::post('catalog-pricing-rules/table-data', [CatalogPricingRulesController::class, 'tableData']);
        Route::match(['post', 'delete'], 'catalog-pricing-rules/delete', [CatalogPricingRulesController::class, 'destroy']);
        Route::post('catalog-pricing-rules/update-status', [CatalogPricingRulesController::class, 'updateStatus']);

        Route::post('catalog-pricing/filter', [CatalogPricingController::class, 'filter']);
        Route::post('catalog-pricing/prices', [CatalogPricingController::class, 'prices']);
        Route::get('catalog-pricing/queue-status', [CatalogPricingController::class, 'queueStatus']);
    });
});

// OrdersController extends the plain Yii2 Controller (not BaseCpController) — its init() only
// ever checked commerce-manageOrders, not accessPlugin-commerce.
Route::middleware(['auth', 'can:commerce-manageOrders'])->group(function () {
    Route::post('orders/fulfill', [OrdersController::class, 'fulfill']);
    Route::get('orders/fulfillment-modal', [OrdersController::class, 'fulfillmentModal']);
    Route::post('orders/save', [OrdersController::class, 'store']);
    Route::post('orders/delete-order', [OrdersController::class, 'destroy']);
    Route::post('orders/refresh', [OrdersController::class, 'refresh']);
    Route::post('orders/get-shipping-method-options', [OrdersController::class, 'shippingMethodOptions']);
    Route::get('orders/user-orders-table', [OrdersController::class, 'userOrdersTable']);
    Route::get('orders/purchasables-table', [OrdersController::class, 'purchasablesTable']);
    Route::get('orders/customer-search', [OrdersController::class, 'customerSearch']);
    Route::get('orders/get-customer-addresses', [OrdersController::class, 'customerAddresses']);
    Route::get('orders/get-order-address', [OrdersController::class, 'orderAddress']);
    Route::post('orders/validate-address', [OrdersController::class, 'validateAddress']);
    Route::post('orders/create-customer', [OrdersController::class, 'createCustomer']);
    Route::get('orders/get-load-cart-url', [OrdersController::class, 'loadCartUrl']);
    Route::get('orders/send-email', [OrdersController::class, 'sendEmail']);
    Route::get('orders/update-order-address', [OrdersController::class, 'updateOrderAddress']);
    Route::get('orders/get-index-sources-badge-counts', [OrdersController::class, 'indexSourcesBadgeCounts']);
    Route::get('orders/get-payment-modal', [OrdersController::class, 'paymentModal']);
    Route::post('orders/payment-amount-data', [OrdersController::class, 'paymentAmountData']);

    Route::post('orders/copy-address-to-user', [OrdersController::class, 'copyAddressToUser'])
        ->middleware('can:editUsers');

    Route::middleware('can:commerce-capturePayment')
        ->post('orders/transaction-capture', [OrdersController::class, 'transactionCapture']);
    Route::middleware('can:commerce-refundPayment')
        ->post('orders/transaction-refund', [OrdersController::class, 'transactionRefund']);

    Route::middleware([RequireCpRequest::class, 'can:deleteUsers'])->group(function () {
        Route::get('orders/reassign-modal', [OrdersController::class, 'reassignModal']);
        Route::post('orders/reassign', [OrdersController::class, 'reassign']);
        Route::get('orders/remove-customer-data-modal', [OrdersController::class, 'removeCustomerDataModal']);
        Route::post('orders/remove-customer-data', [OrdersController::class, 'removeCustomerData']);
    });
});

// InventoryController checks commerce-manageInventoryStockLevels inline on every action
// (not via init()) — replicated here as a route-group-wide permission instead.
Route::middleware(['auth', 'can:commerce-manageInventoryStockLevels'])->group(function () {
    Route::post('inventory/item-save', [InventoryController::class, 'storeItem']);
    Route::post('inventory/inventory-levels-table-data', [InventoryController::class, 'tableData']);
    Route::get('inventory/prepare-update-levels-modal', [InventoryController::class, 'prepareUpdateLevelsModal']);
    Route::post('inventory/update-levels', [InventoryController::class, 'updateLevels']);
    Route::get('inventory/prepare-movement-modal', [InventoryController::class, 'prepareMovementModal']);
    Route::post('inventory/save-inventory-movement', [InventoryController::class, 'storeMovement']);
});

Route::middleware(['auth', 'can:commerce-manageInventoryLocations'])->group(function () {
    Route::post('inventory-locations/save', [InventoryLocationsController::class, 'store']);
    Route::post('inventory-locations/render-ui', [InventoryLocationsController::class, 'renderUi']);
    Route::get('inventory-locations/prepare-delete-modal', [InventoryLocationsController::class, 'prepareDeleteModal']);
    Route::match(['post', 'delete'], 'inventory-locations/deactivate', [InventoryLocationsController::class, 'deactivate']);
});

Route::middleware(['auth', 'can:commerce-manageInventoryTransfers'])->group(function () {
    Route::get('transfers/create', [TransfersController::class, 'create']);
    Route::post('transfers/mark-as-pending', [TransfersController::class, 'markAsPending']);
    Route::post('transfers/receive-transfer', [TransfersController::class, 'receive']);
    Route::get('transfers/prepare-receive-modal', [TransfersController::class, 'prepareReceiveModal']);
});

Route::middleware(['auth', 'can:accessPlugin-commerce', RequireAdmin::class])->group(function () {
    Route::post('emails/save', [EmailsController::class, 'store']);
    Route::match(['post', 'delete'], 'emails/delete', [EmailsController::class, 'destroy']);

    Route::post('pdfs/save', [PdfsController::class, 'store']);
    Route::match(['post', 'delete'], 'pdfs/delete', [PdfsController::class, 'destroy']);
    Route::post('pdfs/reorder', [PdfsController::class, 'reorder']);
});

Route::middleware(['auth', 'can:accessPlugin-commerce'])->group(function () {
    Route::post('catalog-pricing/purchasable-prices', [CatalogPricingController::class, 'purchasablePrices']);

    Route::post('formulas/validate-condition', [FormulasController::class, 'validateCondition']);
    Route::post('formulas/validate-formula', [FormulasController::class, 'validateFormula']);

    Route::post('products/create', CreateProductController::class);
});

// Rendered inside an iframe from the email edit screen's preview button — admin-only, matching
// the legacy controller's plain `requireAdmin(false)` (it never extended a Commerce base
// controller, so there was never an accessPlugin-commerce check here either).
Route::middleware(RequireAdmin::class)->get('email-preview/render', [EmailPreviewController::class, 'render']);

// Anonymous by design — customers download/request order PDFs without being logged in.
// pdf-challenge is rate-limited (not auth-gated) to blunt brute-forcing of order numbers/hashes.
Route::get('downloads/pdf', [DownloadsController::class, 'pdf']);
Route::get('downloads/email-challenge', [DownloadsController::class, 'emailChallenge']);
Route::post('downloads/pdf-challenge', [DownloadsController::class, 'pdfChallenge'])
    ->middleware('throttle:' . PdfChallengeRateLimiter::NAME);
Route::get('downloads/pdf-sent', [DownloadsController::class, 'pdfSent']);
