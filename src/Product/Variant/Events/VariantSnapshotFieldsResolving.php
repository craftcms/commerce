<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Product\Variant\Events;

use CraftCms\Commerce\Product\Variant\Elements\Variant;

/**
 * Dispatched before a variant's snapshot is captured. Listeners may add custom field handles to `$fields`; custom fields aren't captured by default.
 */
class VariantSnapshotFieldsResolving
{
    public function __construct(
        public Variant $variant,
        /** @var string[]|null */
        public ?array $fields = null,
    ) {
    }
}
