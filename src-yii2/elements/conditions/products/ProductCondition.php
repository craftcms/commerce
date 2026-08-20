<?php

namespace craft\commerce\elements\conditions\products;

/** @deprecated use {@see \CraftCms\Commerce\Catalog\Conditions\ProductCondition} */
class_alias(\CraftCms\Commerce\Catalog\Conditions\ProductCondition::class, 'craft\commerce\elements\conditions\products\ProductCondition');

/** @phpstan-ignore-next-line */
if (false) {
    class ProductCondition extends \CraftCms\Commerce\Catalog\Conditions\ProductCondition {}
}
