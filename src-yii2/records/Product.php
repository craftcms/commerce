<?php

namespace craft\commerce\records;

/** @deprecated use {@see \CraftCms\Commerce\Product\Models\Product} */
class_alias(\CraftCms\Commerce\Product\Models\Product::class, 'craft\commerce\records\Product');

/** @phpstan-ignore-next-line */
if (false) {
    class Product extends \CraftCms\Commerce\Product\Models\Product {}
}
