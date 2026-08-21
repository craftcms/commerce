<?php

namespace craft\commerce\elements;

/** @deprecated use {@see \CraftCms\Commerce\Catalog\Elements\Product} */
class_alias(\CraftCms\Commerce\Catalog\Elements\Product::class, 'craft\commerce\elements\Product');

/** @phpstan-ignore-next-line */
if (false) {
    class Product extends \CraftCms\Commerce\Catalog\Elements\Product {}
}
