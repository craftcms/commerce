<?php

namespace craft\commerce\fields;

/** @deprecated use {@see \CraftCms\Commerce\Product\Fields\Products} */
class_alias(\CraftCms\Commerce\Product\Fields\Products::class, 'craft\commerce\fields\Products');

/** @phpstan-ignore-next-line */
if (false) {
    class Products extends \CraftCms\Commerce\Product\Fields\Products {}
}
