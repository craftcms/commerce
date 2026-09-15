<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Store\Concerns;

use CraftCms\Commerce\Store\Stores;

trait StoreTrait
{
    public ?int $storeId = null;

    public function getStore(): \CraftCms\Commerce\Store\Data\Store
    {
        if (!$store = app(Stores::class)->getStoreById($this->storeId)) {
            throw new \InvalidArgumentException('Invalid store ID: ' . $this->storeId);
        }

        return $store;
    }
}
