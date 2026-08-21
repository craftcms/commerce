<?php

namespace craft\commerce\models;

/** @deprecated use {@see \CraftCms\Commerce\Catalog\Models\ProductTypeSite} */
class_alias(\CraftCms\Commerce\Catalog\Models\ProductTypeSite::class, 'craft\commerce\models\ProductTypeSite');

/** @phpstan-ignore-next-line */
if (false) {
    class ProductTypeSite extends \CraftCms\Commerce\Catalog\Models\ProductTypeSite {}
}
