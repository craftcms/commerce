<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Promotion\Events;

use craft\commerce\models\Sale;
use CraftCms\Cms\Shared\Concerns\ValidatableEvent;
use CraftCms\Commerce\Purchasable\Contracts\PurchasableInterface;

class SaleMatchEvent
{
    use ValidatableEvent;

    public Sale $sale;
    public PurchasableInterface $purchasable;
    public bool $isNew;
}
