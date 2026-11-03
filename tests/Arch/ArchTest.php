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
        CraftCms\Commerce\Email\Emails::class,
        CraftCms\Commerce\Helpers\Locale::class,
        CraftCms\Commerce\Http\Controllers\DownloadsController::class,
        CraftCms\Commerce\Http\Controllers\OrdersController::class,
        CraftCms\Commerce\Http\Controllers\Settings\EmailsController::class,
        CraftCms\Commerce\Http\Controllers\Settings\OrderStatusesController::class,
        CraftCms\Commerce\Http\Controllers\Settings\StoresController::class,
        CraftCms\Commerce\Http\Controllers\StoreManagement\ShippingRulesController::class,
        CraftCms\Commerce\Http\Controllers\StoreManagement\StoreManagementController::class,
        CraftCms\Commerce\Order\Elements\Order::class,
        CraftCms\Commerce\Order\Events\PurgeAddressesEvent::class,
        CraftCms\Commerce\Order\LineItemStatuses::class,
        CraftCms\Commerce\Order\OrderStatuses::class,
        CraftCms\Commerce\Pdf\Pdfs::class,
        CraftCms\Commerce\Plugin\Concerns\HasLegacyBridges::class,
        CraftCms\Commerce\Product\ProductType\Data\ProductType::class,
        CraftCms\Commerce\Product\ProductType\ProductTypes::class,
        CraftCms\Commerce\Purchasable\Conditions\PurchasableConditionRule::class,
        CraftCms\Commerce\Purchasable\Elements\Purchasable::class,
        CraftCms\Commerce\Purchasable\Events\ModifyPurchasablesTableQueryEvent::class,
        CraftCms\Commerce\Store\Stores::class,
        CraftCms\Commerce\Support\Expressions\LocalTimestamp::class,
    ]);
