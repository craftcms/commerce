<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Catalog\Events;

use craft\commerce\elements\Variant;

class PurchaseVariantEvent
{
    public Variant $variant;
}
