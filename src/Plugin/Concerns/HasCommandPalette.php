<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Plugin\Concerns;

use CraftCms\Cms\Cp\CommandPalette\ActionRegistry;
use CraftCms\Cms\Cp\CommandPalette\PageRegistry;
use CraftCms\Cms\Cp\CommandPalette\SearchProviderRegistry;
use CraftCms\Cms\Cp\CommandPalette\SearchProviders\ElementSearchProvider;
use CraftCms\Cms\User\Contracts\CraftUser;
use CraftCms\Commerce\CommandPalette\CommerceActions;
use CraftCms\Commerce\CommandPalette\CommercePages;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Plugin;
use CraftCms\Commerce\Product\Elements\Product;
use CraftCms\Commerce\Product\ProductType\ProductTypes;
use CraftCms\Commerce\Product\Variant\Elements\Variant;

/**
 * @mixin Plugin
 */
trait HasCommandPalette
{
    private function registerCommandPalette(): void
    {
        app(PageRegistry::class)->register(
            self::HANDLE,
            fn(CraftUser $user, CommercePages $pages) => $pages->all($user),
        );

        app(ActionRegistry::class)->register(
            self::HANDLE,
            fn(CraftUser $user, CommerceActions $actions) => $actions->all($user),
        );

        $hasViewableProductTypes = fn() => app(ProductTypes::class)->getViewableProductTypeIds(true) !== [];

        app(SearchProviderRegistry::class)->register(
            new ElementSearchProvider(Order::class, fn(CraftUser $user) => $user->can('commerce-manageOrders')),
            new ElementSearchProvider(Product::class, $hasViewableProductTypes),
            new ElementSearchProvider(Variant::class, $hasViewableProductTypes),
        );
    }
}
