<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Events;

use CraftCms\Cms\Shared\Concerns\ValidatableEvent;
use CraftCms\Commerce\Order\LineItem\Data\LineItem;

class AddLineItemEvent
{
    use ValidatableEvent;

    public LineItem $lineItem;
    public bool $isNew = false;
}
