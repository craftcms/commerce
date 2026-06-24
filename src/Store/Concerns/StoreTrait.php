<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Store\Concerns;

use craft\commerce\Plugin;

trait StoreTrait
{
    public ?int $storeId = null;

    public function getStore(): \craft\commerce\models\Store
    {
        // TODO: migrate to app(Stores::class)->getStoreById() once service migrated to src/
        /** @phpstan-ignore-next-line */
        if (!$store = Plugin::getInstance()->getStores()->getStoreById($this->storeId)) {
            throw new \InvalidArgumentException('Invalid store ID: ' . $this->storeId);
        }

        return $store;
    }
}
