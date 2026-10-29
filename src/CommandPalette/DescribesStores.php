<?php

declare(strict_types=1);

namespace CraftCms\Commerce\CommandPalette;

use CraftCms\Commerce\Store\Data\Store;

use function CraftCms\Cms\t;

trait DescribesStores
{
    /** Names the store after the description, when there's more than one to tell apart. */
    private function storeDescription(string $description, Store $store, int $storeCount): string
    {
        return $storeCount > 1 ? sprintf('%s → %s', $description, t($store->name ?? '', category: 'site')) : $description;
    }
}
