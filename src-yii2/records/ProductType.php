<?php

namespace craft\commerce\records;

/** @deprecated use {@see \CraftCms\Commerce\Product\ProductType\Models\ProductType} */
class_alias(\CraftCms\Commerce\Product\ProductType\Models\ProductType::class, 'craft\commerce\records\ProductType');

/** @phpstan-ignore-next-line */
if (false) {
    class ProductType extends \CraftCms\Commerce\Product\ProductType\Models\ProductType {}
}
