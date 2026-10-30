<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\Concerns;

use CraftCms\Cms\Cp\Data\NavItem;
use CraftCms\Commerce\Store\Data\Store;
use CraftCms\Commerce\Store\Stores;

use function CraftCms\Cms\cp_url;
use function CraftCms\Cms\t;

/**
 * For settings that are managed store by store: a subnav listing the stores, and the crumb
 * for the store being shown. Each store's screen lives at `<path>/<store handle>`.
 */
trait HasStoreSubnav
{
    use HasSubnavCrumbMenu;

    /**
     * The stores, under a "Stores" heading. Empty when there's only one store, since there's
     * nothing to switch between.
     *
     * @return NavItem[]
     */
    protected function storeSubnav(string $path, Store $currentStore): array
    {
        $stores = app(Stores::class)->getAllStores();

        if ($stores->count() < 2) {
            return [];
        }

        return [
            new NavItem()
                ->label(t('Stores', category: 'commerce'))
                ->group(true)
                ->subnav($stores
                    ->map(fn(Store $store) => new NavItem()
                        ->label(t($store->getName(), category: 'site'))
                        ->url(cp_url("$path/$store->handle"))
                        ->selected($store->id === $currentStore->id))
                    ->values()
                    ->all()),
        ];
    }

    /**
     * The crumb for the store being shown, with the other stores in its menu.
     *
     * @return array{label: string, url: string, items: list<array<string, mixed>>}
     */
    protected function storeCrumb(string $path, Store $store): array
    {
        $url = cp_url("$path/$store->handle");

        return [
            'label' => t($store->getName(), category: 'site'),
            'url' => $url,
            'items' => $this->subnavCrumbMenu($this->storeSubnav($path, $store), $url),
        ];
    }
}
