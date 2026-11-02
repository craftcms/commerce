<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Product\Variant\Events;

use CraftCms\Commerce\Product\Variant\Elements\Variant;

/**
 * Dispatched after a variant's snapshot is captured. Listeners may customize, extend, or redact `$fieldData`.
 */
class VariantSnapshotDataResolving
{
    public function __construct(
        public Variant $variant,
        public array $fieldData,
    ) {
    }
}
