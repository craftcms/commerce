<?php

namespace craft\commerce\records;

/** @deprecated use {@see \CraftCms\Commerce\Catalog\Models\Variant} */
class_alias(\CraftCms\Commerce\Catalog\Models\Variant::class, 'craft\commerce\records\Variant');

/** @phpstan-ignore-next-line */
if (false) {
    class Variant extends \CraftCms\Commerce\Catalog\Models\Variant {}
}
