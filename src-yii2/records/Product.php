<?php

namespace craft\commerce\records;

/** @deprecated use {@see \CraftCms\Commerce\Catalog\Models\Product} */
class_alias(\CraftCms\Commerce\Catalog\Models\Product::class, 'craft\commerce\records\Product');

/** @phpstan-ignore-next-line */
if (false) {
    class Product extends \CraftCms\Commerce\Catalog\Models\Product {}
}
