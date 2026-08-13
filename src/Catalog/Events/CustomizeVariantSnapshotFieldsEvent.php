<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Catalog\Events;

use CraftCms\Commerce\Catalog\Elements\Variant;

class CustomizeVariantSnapshotFieldsEvent
{
    public function __construct(
        public Variant $variant,
        public ?array $fields = null,
    ) {
    }
}
