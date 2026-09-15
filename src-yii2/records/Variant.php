<?php

namespace craft\commerce\records;

/** @deprecated use {@see \CraftCms\Commerce\Product\Variant\Models\Variant} */
class_alias(\CraftCms\Commerce\Product\Variant\Models\Variant::class, 'craft\commerce\records\Variant');

/** @phpstan-ignore-next-line */
if (false) {
    class Variant extends \CraftCms\Commerce\Product\Variant\Models\Variant {}
}
