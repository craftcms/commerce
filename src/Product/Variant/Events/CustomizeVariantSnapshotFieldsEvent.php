<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Product\Variant\Events;

use CraftCms\Commerce\Product\Variant\Elements\Variant;

class CustomizeVariantSnapshotFieldsEvent
{
    public function __construct(
        public Variant $variant,
        public ?array $fields = null,
    ) {
    }
}
