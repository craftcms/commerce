<?php

namespace craft\commerce\elements;

/** @deprecated use {@see \CraftCms\Commerce\Product\Elements\Product} */
class_alias(\CraftCms\Commerce\Product\Elements\Product::class, 'craft\commerce\elements\Product');

/** @phpstan-ignore-next-line */
if (false) {
    class Product extends \CraftCms\Commerce\Product\Elements\Product {}
}
