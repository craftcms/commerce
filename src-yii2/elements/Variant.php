<?php

namespace craft\commerce\elements;

/** @deprecated use {@see \CraftCms\Commerce\Catalog\Elements\Variant} */
class_alias(\CraftCms\Commerce\Catalog\Elements\Variant::class, 'craft\commerce\elements\Variant');

/** @phpstan-ignore-next-line */
if (false) {
    class Variant extends \CraftCms\Commerce\Catalog\Elements\Variant {}
}
