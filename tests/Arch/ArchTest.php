<?php

declare(strict_types=1);

arch('No debug functions')
    ->expect('CraftCms\\Commerce')
    ->not->toUse(['die', 'dd', 'dump', 'env']);

// TODO: remove each class from this list as it's ported off Yii and the legacy Craft classes, so Commerce can run without craftcms/yii2-adapter.
arch('src/ does not use Yii or legacy Craft classes')
    ->expect('CraftCms\\Commerce')
    ->not->toUse(['yii', 'craft', 'Craft', 'Yii'])
    ->ignoring([
        'craft\\commerce',
        CraftCms\Commerce\CatalogPricing\CatalogPricing::class,
        CraftCms\Commerce\Customer\Customers::class,
        CraftCms\Commerce\Dashboard\Widgets\AverageOrderTotal::class,
        CraftCms\Commerce\Dashboard\Widgets\NewCustomers::class,
        CraftCms\Commerce\Dashboard\Widgets\Orders::class,
        CraftCms\Commerce\Dashboard\Widgets\RepeatCustomers::class,
        CraftCms\Commerce\Dashboard\Widgets\TopCustomers::class,
        CraftCms\Commerce\Dashboard\Widgets\TopProducts::class,
        CraftCms\Commerce\Dashboard\Widgets\TopProductTypes::class,
        CraftCms\Commerce\Dashboard\Widgets\TopPurchasables::class,
        CraftCms\Commerce\Dashboard\Widgets\TotalOrders::class,
        CraftCms\Commerce\Dashboard\Widgets\TotalOrdersByCountry::class,
        CraftCms\Commerce\Dashboard\Widgets\TotalRevenue::class,
        CraftCms\Commerce\Email\Emails::class,
        CraftCms\Commerce\Email\Events\EmailEvent::class,
        CraftCms\Commerce\Email\Events\MailEvent::class,
        CraftCms\Commerce\Helpers\Locale::class,
        CraftCms\Commerce\Http\Controllers\Concerns\HasStoreManagementScreen::class,
        CraftCms\Commerce\Http\Controllers\DownloadsController::class,
        CraftCms\Commerce\Http\Controllers\OrdersController::class,
        CraftCms\Commerce\Http\Controllers\Settings\EmailsController::class,
        CraftCms\Commerce\Http\Controllers\Settings\OrderStatusesController::class,
        CraftCms\Commerce\Http\Controllers\Settings\StoresController::class,
        CraftCms\Commerce\Http\Controllers\StoreManagement\CatalogPricingController::class,
        CraftCms\Commerce\Http\Controllers\StoreManagement\SalesController::class,
        CraftCms\Commerce\Http\Controllers\StoreManagement\ShippingRulesController::class,
        CraftCms\Commerce\Http\Controllers\StoreManagement\StoreManagementController::class,
        CraftCms\Commerce\Http\Controllers\Users\UsersController::class,
        CraftCms\Commerce\Inventory\Events\InventoryMovementEvent::class,
        CraftCms\Commerce\Inventory\Events\UpdateInventoryLevelEvent::class,
        CraftCms\Commerce\Order\Adjuster\Discount::class,
        CraftCms\Commerce\Order\Elements\Order::class,
        CraftCms\Commerce\Order\Events\PurgeAddressesEvent::class,
        CraftCms\Commerce\Order\LineItemStatuses::class,
        CraftCms\Commerce\Order\OrderStatuses::class,
        CraftCms\Commerce\Payment\Gateway\Gateways::class,
        CraftCms\Commerce\Payment\Payments::class,
        CraftCms\Commerce\Pdf\Events\PdfEvent::class,
        CraftCms\Commerce\Pdf\Events\PdfRenderEvent::class,
        CraftCms\Commerce\Pdf\Events\PdfRenderOptionsEvent::class,
        CraftCms\Commerce\Pdf\Pdfs::class,
        CraftCms\Commerce\Plugin\Concerns\HasLegacyBridges::class,
        CraftCms\Commerce\Product\ProductType\Data\ProductType::class,
        CraftCms\Commerce\Product\ProductType\ProductTypes::class,
        CraftCms\Commerce\Promotion\Discounts::class,
        CraftCms\Commerce\Promotion\Events\DiscountAdjustmentsEvent::class,
        CraftCms\Commerce\Promotion\Sales::class,
        CraftCms\Commerce\Purchasable\Assets\PurchasablePriceFieldAsset::class,
        CraftCms\Commerce\Purchasable\Conditions\CatalogPricingRulePurchasableCategoryConditionRule::class,
        CraftCms\Commerce\Purchasable\Conditions\PurchasableConditionRule::class,
        CraftCms\Commerce\Purchasable\Elements\Purchasable::class,
        CraftCms\Commerce\Purchasable\Events\ModifyPurchasablesTableQueryEvent::class,
        CraftCms\Commerce\Store\Events\StoreEvent::class,
        CraftCms\Commerce\Store\Stores::class,
        CraftCms\Commerce\Support\Expressions\LocalTimestamp::class,
        CraftCms\Commerce\Tax\Events\TaxEngineEvent::class,
        CraftCms\Commerce\Tax\Events\TaxIdValidatorsEvent::class,
    ]);
