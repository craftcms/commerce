<?php

namespace craft\commerce\elements\conditions\products;

/** @deprecated use {@see \CraftCms\Commerce\Product\Conditions\ProductCondition} */
class_alias(\CraftCms\Commerce\Product\Conditions\ProductCondition::class, 'craft\commerce\elements\conditions\products\ProductCondition');

/** @phpstan-ignore-next-line */
if (false) {
    class ProductCondition extends \CraftCms\Commerce\Product\Conditions\ProductCondition {}
}
