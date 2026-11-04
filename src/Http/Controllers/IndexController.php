<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers;

use CraftCms\Commerce\Plugin;
use CraftCms\Commerce\Product\ProductType\ProductTypes;
use CraftCms\Commerce\Store\Stores;
use Illuminate\Http\RedirectResponse;

use function CraftCms\Cms\cp_url;
use function CraftCms\Cms\currentUser;

/**
 * Sends the user to the first Commerce section they have access to, preferring the
 * `defaultView` setting.
 */
readonly class IndexController
{
    private const array DEFAULT_VIEW_PERMISSIONS = [
        'commerce/orders' => 'commerce-manageOrders',
        'commerce/inventory' => 'commerce-manageInventoryStockLevels',
        'commerce/store-management' => 'commerce-manageStoreSettings',
        'commerce/promotions' => 'commerce-managePromotions',
        'commerce/shipping/shippingmethods' => 'commerce-manageShipping',
        'commerce/tax/taxrates' => 'commerce-manageTaxes',
    ];

    public function __construct(
        private Plugin $plugin,
        private ProductTypes $productTypes,
        private Stores $stores,
    ) {
    }

    public function __invoke(): RedirectResponse
    {
        $user = currentUser();
        $defaultView = $this->plugin->getSettings()->defaultView;
        $hasViewableProductTypes = $this->productTypes->getViewableProductTypes() !== [];
        $storeHandle = $this->stores->getPrimaryStore()?->handle;

        // Legacy default views that now live under the primary store's store management area.
        $storeRoutes = $storeHandle ? [
            'commerce/promotions' => "commerce/store-management/$storeHandle/discounts",
            'commerce/shipping/shippingmethods' => "commerce/store-management/$storeHandle/shippingmethods",
            'commerce/tax/taxrates' => "commerce/store-management/$storeHandle/taxrates",
        ] : [];

        $permission = self::DEFAULT_VIEW_PERMISSIONS[$defaultView] ?? null;

        if ($permission && $user?->can($permission)) {
            return redirect(cp_url($storeRoutes[$defaultView] ?? $defaultView));
        }

        if ($defaultView === 'commerce/products' && $hasViewableProductTypes) {
            return redirect(cp_url('commerce/products'));
        }

        if ($user?->can('commerce-manageOrders')) {
            return redirect(cp_url('commerce/orders'));
        }

        if ($hasViewableProductTypes) {
            return redirect(cp_url('commerce/products'));
        }

        $fallbacks = [
            'commerce-manageStoreSettings' => 'commerce/store-management',
            'commerce-manageInventoryStockLevels' => 'commerce/inventory',
        ];

        if ($storeHandle) {
            $fallbacks['commerce-managePromotions'] = "commerce/store-management/$storeHandle/discounts";
            $fallbacks['commerce-manageShipping'] = "commerce/store-management/$storeHandle/shippingmethods";
            $fallbacks['commerce-manageTaxes'] = "commerce/store-management/$storeHandle/taxrates";
        }

        foreach ($fallbacks as $permission => $path) {
            if ($user?->can($permission)) {
                return redirect(cp_url($path));
            }
        }

        abort(403);
    }
}
