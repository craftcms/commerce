<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Promotion\Events;

use craft\commerce\models\Sale;

class SaleEvent
{
    public Sale $sale;
    public bool $isNew = false;
}
