<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Events;

use craft\commerce\models\LineItem;

class LineItemEvent
{
    public LineItem $lineItem;
    public bool $isNew = false;
}
