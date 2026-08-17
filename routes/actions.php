<?php

use CraftCms\Cms\Http\Middleware\RequireAdmin;
use CraftCms\Cms\Http\Middleware\RequireCpRequest;
use CraftCms\Commerce\Http\Controllers\CartController;
use CraftCms\Commerce\Http\Controllers\DonationsController;
use CraftCms\Commerce\Http\Controllers\OrdersController;
use CraftCms\Commerce\Http\Controllers\Settings\CatalogPricingController;
use CraftCms\Commerce\Http\Controllers\Settings\CatalogPricingRulesController;
use CraftCms\Commerce\Http\Controllers\DownloadsController;
use CraftCms\Commerce\Http\Controllers\EmailPreviewController;
use CraftCms\Commerce\Http\Controllers\Settings\DiscountsController;
use CraftCms\Commerce\Http\Controllers\Settings\EmailsController;
use CraftCms\Commerce\Http\Controllers\FormulasController;
use CraftCms\Commerce\Http\Controllers\Settings\GatewaysController;
use CraftCms\Commerce\Http\Controllers\InventoryController;
use CraftCms\Commerce\Http\Controllers\InventoryLocationsController;
use CraftCms\Commerce\Http\Controllers\Settings\LineItemStatusesController;
use CraftCms\Commerce\Http\Controllers\Settings\OrderSettingsController;
use CraftCms\Commerce\Http\Controllers\Settings\OrderStatusesController;
use CraftCms\Commerce\Http\Controllers\Settings\PaymentCurrenciesController;
use CraftCms\Commerce\Http\Controllers\PaymentSourcesController;
use CraftCms\Commerce\Http\Controllers\PaymentsController;
use CraftCms\Commerce\Http\Controllers\Settings\PdfsController;
use CraftCms\Commerce\Http\Controllers\Settings\ProductTypesController;
use CraftCms\Commerce\Http\Controllers\Settings\SalesController;
use CraftCms\Commerce\Http\Controllers\Settings\SettingsController;
use CraftCms\Commerce\Http\Controllers\Settings\ShippingCategoriesController;
use CraftCms\Commerce\Http\Controllers\Settings\ShippingMethodsController;
use CraftCms\Commerce\Http\Controllers\Settings\ShippingRulesController;
use CraftCms\Commerce\Http\Controllers\Settings\ShippingZonesController;
use CraftCms\Commerce\Http\Controllers\Settings\StoreManagementController;
use CraftCms\Commerce\Http\Controllers\Settings\StoresController;
use CraftCms\Commerce\Http\Controllers\Settings\TaxCategoriesController;
use CraftCms\Commerce\Http\Controllers\Settings\TaxRatesController;
use CraftCms\Commerce\Http\Controllers\Settings\TaxZonesController;
use CraftCms\Commerce\Http\Controllers\TransfersController;
use CraftCms\Commerce\Http\Controllers\UserOrdersController;
use CraftCms\Commerce\Http\Controllers\WebhooksController;
use CraftCms\Commerce\Http\RateLimiters\CartChallengeRateLimiter;
use CraftCms\Commerce\Http\RateLimiters\CartRateLimiter;
use CraftCms\Commerce\Http\RateLimiters\PdfChallengeRateLimiter;
use Illuminate\Support\Facades\Route;

Route::post('webhooks/process-webhook', [WebhooksController::class, 'processWebhook']);

Route::match(['get', 'post'], 'user-orders/get-orders', [UserOrdersController::class, 'getOrders']);

// Anonymous by design — carts are usable by guests. Rate-limited (not auth-gated) to blunt
// enumeration/brute-force attempts against the number/couponCode params.
Route::middleware('throttle:' . CartRateLimiter::NAME)->group(function () {
    Route::get('cart/get-cart', [CartController::class, 'getCart']);
    Route::post('cart/update-cart', [CartController::class, 'updateCart']);
    Route::match(['get', 'post'], 'cart/load-cart', [CartController::class, 'loadCart']);
    Route::post('cart/complete', [CartController::class, 'complete']);
});

Route::post('cart/forget-cart', [CartController::class, 'forgetCart']);
Route::get('cart/email-challenge', [CartController::class, 'emailChallenge']);
Route::post('cart/cart-challenge', [CartController::class, 'cartChallenge'])
    ->middleware('throttle:' . CartChallengeRateLimiter::NAME);
Route::get('cart/cart-sent', [CartController::class, 'cartSent']);

// Anonymous by design — guest checkout must be able to pay. Each action gates its own
// order/customer-ownership checks inline (mirrors CartController's own permission model).
Route::post('payments/pay', [PaymentsController::class, 'pay']);
Route::match(['get', 'post'], 'payments/complete-payment', [PaymentsController::class, 'completePayment']);

Route::post('payment-sources/add', [PaymentSourcesController::class, 'add']);
Route::post('payment-sources/set-primary-payment-source', [PaymentSourcesController::class, 'setPrimaryPaymentSource']);
Route::post('payment-sources/delete', [PaymentSourcesController::class, 'delete']);

// These are also reachable, unauthenticated, at their site-side action URL (per
// CraftCms\Cms\Plugin\Concerns\HasRoutes::registerActionRoutes()) — the `auth`/`can`
// middleware below is what actually protects them, not the URL prefix.
Route::middleware(['auth', 'can:accessPlugin-commerce', 'can:commerce-manageDonationSettings'])
    ->post('donations/save', [DonationsController::class, 'save']);

Route::middleware(['auth', 'can:accessPlugin-commerce', RequireAdmin::class])->group(function () {
    Route::post('gateways/save', [GatewaysController::class, 'save']);
    Route::post('gateways/archive', [GatewaysController::class, 'archive']);
    Route::post('gateways/reorder', [GatewaysController::class, 'reorder']);

    Route::post('settings/save-settings', [SettingsController::class, 'saveSettings']);
    Route::post('settings/save-transfer-settings', [SettingsController::class, 'saveTransferSettings']);
    Route::post('order-settings/save', [OrderSettingsController::class, 'save']);

    Route::post('stores/save-store', [StoresController::class, 'saveStore']);
    Route::post('stores/delete-store', [StoresController::class, 'deleteStore']);
    Route::post('stores/reorder-stores', [StoresController::class, 'reorderStores']);
    Route::post('stores/save-site-stores', [StoresController::class, 'saveSiteStores']);

    Route::post('order-statuses/save', [OrderStatusesController::class, 'save']);
    Route::match(['get', 'post'], 'order-statuses/get-order-statuses', [OrderStatusesController::class, 'getOrderStatuses']);
    Route::post('order-statuses/reorder', [OrderStatusesController::class, 'reorder']);
    Route::post('order-statuses/delete', [OrderStatusesController::class, 'delete']);

    Route::post('line-item-statuses/save', [LineItemStatusesController::class, 'save']);
    Route::post('line-item-statuses/reorder', [LineItemStatusesController::class, 'reorder']);
    Route::post('line-item-statuses/archive', [LineItemStatusesController::class, 'archive']);

    Route::post('product-types/save-product-type', [ProductTypesController::class, 'saveProductType']);
    Route::post('product-types/delete-product-type', [ProductTypesController::class, 'deleteProductType']);
});

// BaseStoreManagementController::init() always required commerce-manageStoreSettings, on top of
// whichever more specific permission each feature area's own controller adds — every route in
// this group must check both, matching that legacy compound check exactly.
Route::middleware(['auth', 'can:accessPlugin-commerce', 'can:commerce-manageStoreSettings'])->group(function () {
    Route::post('store-management/save', [StoreManagementController::class, 'save']);

    Route::post('payment-currencies/save', [PaymentCurrenciesController::class, 'save']);
    Route::post('payment-currencies/delete', [PaymentCurrenciesController::class, 'delete']);

    Route::middleware('can:commerce-manageShipping')->group(function () {
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

    Route::middleware('can:commerce-manageTaxes')->group(function () {
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

    Route::middleware('can:commerce-managePromotions')->group(function () {
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
});

// OrdersController extends the plain Yii2 Controller (not BaseCpController) — its init() only
// ever checked commerce-manageOrders, not accessPlugin-commerce.
Route::middleware(['auth', 'can:commerce-manageOrders'])->group(function () {
    Route::post('orders/fulfill', [OrdersController::class, 'fulfill']);
    Route::get('orders/fulfillment-modal', [OrdersController::class, 'fulfillmentModal']);
    Route::post('orders/save', [OrdersController::class, 'save']);
    Route::post('orders/delete-order', [OrdersController::class, 'deleteOrder']);
    Route::post('orders/refresh', [OrdersController::class, 'refresh']);
    Route::post('orders/get-shipping-method-options', [OrdersController::class, 'getShippingMethodOptions']);
    Route::get('orders/user-orders-table', [OrdersController::class, 'userOrdersTable']);
    Route::get('orders/purchasables-table', [OrdersController::class, 'purchasablesTable']);
    Route::get('orders/customer-search', [OrdersController::class, 'customerSearch']);
    Route::get('orders/get-customer-addresses', [OrdersController::class, 'getCustomerAddresses']);
    Route::get('orders/get-order-address', [OrdersController::class, 'getOrderAddress']);
    Route::post('orders/validate-address', [OrdersController::class, 'validateAddress']);
    Route::post('orders/create-customer', [OrdersController::class, 'createCustomer']);
    Route::get('orders/get-load-cart-url', [OrdersController::class, 'getLoadCartUrl']);
    Route::get('orders/send-email', [OrdersController::class, 'sendEmail']);
    Route::get('orders/update-order-address', [OrdersController::class, 'updateOrderAddress']);
    Route::get('orders/get-index-sources-badge-counts', [OrdersController::class, 'getIndexSourcesBadgeCounts']);
    Route::get('orders/get-payment-modal', [OrdersController::class, 'getPaymentModal']);
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
    Route::post('inventory/item-save', [InventoryController::class, 'itemSave']);
    Route::get('inventory/inventory-levels-table-data', [InventoryController::class, 'inventoryLevelsTableData']);
    Route::post('inventory/update-levels', [InventoryController::class, 'updateLevels']);
    Route::get('inventory/edit-update-levels-modal', [InventoryController::class, 'editUpdateLevelsModal']);
    Route::post('inventory/save-inventory-movement', [InventoryController::class, 'saveInventoryMovement']);
    Route::get('inventory/edit-movement-modal', [InventoryController::class, 'editMovementModal']);
    Route::get('inventory/unfulfilled-orders', [InventoryController::class, 'unfulfilledOrders']);
});

Route::middleware(['auth', 'can:commerce-manageInventoryLocations'])->group(function () {
    Route::post('inventory-locations/save', [InventoryLocationsController::class, 'save']);
    Route::get('inventory-locations/inventory-locations-table-data', [InventoryLocationsController::class, 'inventoryLocationsTableData']);
    Route::get('inventory-locations/prepare-delete-modal', [InventoryLocationsController::class, 'prepareDeleteModal']);
    Route::post('inventory-locations/deactivate', [InventoryLocationsController::class, 'deactivate']);
});

Route::middleware(['auth', 'can:commerce-manageInventoryTransfers'])->group(function () {
    Route::get('transfers/create', [TransfersController::class, 'create']);
    Route::post('transfers/mark-as-pending', [TransfersController::class, 'markAsPending']);
    Route::post('transfers/save-settings', [TransfersController::class, 'saveSettings']);
    Route::post('transfers/receive-transfer', [TransfersController::class, 'receiveTransfer']);
    Route::get('transfers/receive-transfer-screen', [TransfersController::class, 'receiveTransferScreen']);
    Route::get('transfers/render-management', [TransfersController::class, 'renderManagement']);
});

Route::middleware(['auth', 'can:accessPlugin-commerce', RequireAdmin::class])->group(function () {
    Route::post('emails/save', [EmailsController::class, 'save']);
    Route::post('emails/delete', [EmailsController::class, 'delete']);

    Route::post('pdfs/save', [PdfsController::class, 'save']);
    Route::post('pdfs/delete', [PdfsController::class, 'delete']);
    Route::post('pdfs/reorder', [PdfsController::class, 'reorder']);
});

Route::middleware(['auth', 'can:accessPlugin-commerce'])->group(function () {
    Route::post('formulas/validate-condition', [FormulasController::class, 'validateCondition']);
    Route::post('formulas/validate-formula', [FormulasController::class, 'validateFormula']);
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
