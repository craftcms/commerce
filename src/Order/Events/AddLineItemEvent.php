<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Events;

use craft\commerce\models\LineItem;
use CraftCms\Cms\Shared\Concerns\ValidatableEvent;

class AddLineItemEvent
{
    use ValidatableEvent;

    public LineItem $lineItem;
    public bool $isNew = false;
}
