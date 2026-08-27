<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Store\Contracts;

use CraftCms\Commerce\Store\Data\Store;

interface HasStoreInterface
{
    public function getStore(): Store;
}
