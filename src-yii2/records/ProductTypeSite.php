<?php

namespace craft\commerce\records;

/** @deprecated use {@see \CraftCms\Commerce\Catalog\ProductType\Models\ProductTypeSite} */
class_alias(\CraftCms\Commerce\Catalog\ProductType\Models\ProductTypeSite::class, 'craft\commerce\records\ProductTypeSite');

/** @phpstan-ignore-next-line */
if (false) {
    class ProductTypeSite extends \CraftCms\Commerce\Catalog\ProductType\Models\ProductTypeSite {}
}
