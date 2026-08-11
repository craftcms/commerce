<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Catalog\Events;

use CraftCms\Commerce\Catalog\Elements\Variant;

class PurchaseVariantEvent
{
    public Variant $variant;
}
