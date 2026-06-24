<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Store\Contracts;

use craft\commerce\models\Store;

interface HasStoreInterface
{
    public function getStore(): Store;
}
