<?php

namespace craft\commerce\models;

/** @deprecated use {@see \CraftCms\Commerce\Product\ProductType\Data\ProductType} */
class_alias(\CraftCms\Commerce\Product\ProductType\Data\ProductType::class, 'craft\commerce\models\ProductType');

/** @phpstan-ignore-next-line */
if (false) {
    class ProductType extends \CraftCms\Commerce\Product\ProductType\Data\ProductType {}
}
