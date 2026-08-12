<?php

namespace craft\commerce\models;

/** @deprecated use {@see \CraftCms\Commerce\Catalog\ProductType\Data\ProductType} */
class_alias(\CraftCms\Commerce\Catalog\ProductType\Data\ProductType::class, 'craft\commerce\models\ProductType');

/** @phpstan-ignore-next-line */
if (false) {
    class ProductType extends \CraftCms\Commerce\Catalog\ProductType\Data\ProductType {}
}
