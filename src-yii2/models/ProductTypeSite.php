<?php

namespace craft\commerce\models;

/** @deprecated use {@see \CraftCms\Commerce\Product\ProductType\Data\ProductTypeSite} */
class_alias(\CraftCms\Commerce\Product\ProductType\Data\ProductTypeSite::class, 'craft\commerce\models\ProductTypeSite');

/** @phpstan-ignore-next-line */
if (false) {
    class ProductTypeSite extends \CraftCms\Commerce\Product\ProductType\Data\ProductTypeSite {}
}
