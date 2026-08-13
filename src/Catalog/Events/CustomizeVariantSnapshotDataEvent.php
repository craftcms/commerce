<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Catalog\Events;

use CraftCms\Commerce\Catalog\Elements\Variant;

class CustomizeVariantSnapshotDataEvent
{
    public function __construct(
        public Variant $variant,
        public array $fieldData,
    ) {
    }
}
