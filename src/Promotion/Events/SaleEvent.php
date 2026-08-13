<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Promotion\Events;

use CraftCms\Commerce\Promotion\Models\Sale;

class SaleEvent
{
    public function __construct(
        public Sale $sale,
        public bool $isNew = false,
    ) {
    }
}
