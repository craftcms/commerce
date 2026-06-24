<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Catalog\Events;

use craft\commerce\elements\Variant;

class CustomizeVariantSnapshotFieldsEvent
{
    public Variant $variant;
    public ?array $fields = null;
}
