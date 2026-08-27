<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Promotion\Events;

use CraftCms\Cms\Shared\Concerns\ValidatableEvent;
use CraftCms\Commerce\Promotion\Data\Sale;
use CraftCms\Commerce\Purchasable\Contracts\PurchasableInterface;

class SaleMatchEvent
{
    use ValidatableEvent;

    public function __construct(
        public Sale $sale,
        public PurchasableInterface $purchasable,
        public bool $isNew,
    ) {
    }
}
