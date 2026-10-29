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
use CraftCms\Commerce\CommandPalette\OrderSearchProvider;
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
            fn(CraftUser $user, CommercePages $pages) => $this->isInstalled ? $pages->all($user) : [],
        );

        app(ActionRegistry::class)->register(
            self::HANDLE,
            fn(CraftUser $user, CommerceActions $actions) => $this->isInstalled ? $actions->all($user) : [],
        );

        // Stores and product types live in tables that don't exist until Commerce is installed.
        $hasViewableProductTypes = fn() => $this->isInstalled && app(ProductTypes::class)->getViewableProductTypeIds(true) !== [];

        app(SearchProviderRegistry::class)->register(
            OrderSearchProvider::class,
            new ElementSearchProvider(Product::class, $hasViewableProductTypes),
            new ElementSearchProvider(Variant::class, $hasViewableProductTypes),
        );
    }
}
