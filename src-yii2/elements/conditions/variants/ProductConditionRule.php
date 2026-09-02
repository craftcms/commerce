<?php

namespace craft\commerce\elements\conditions\variants;

/** @deprecated use {@see \CraftCms\Commerce\Product\Variant\Conditions\VariantProductConditionRule} */
class_alias(\CraftCms\Commerce\Product\Variant\Conditions\VariantProductConditionRule::class, 'craft\commerce\elements\conditions\variants\ProductConditionRule');

/** @phpstan-ignore-next-line */
if (false) {
    class ProductConditionRule extends \CraftCms\Commerce\Product\Variant\Conditions\VariantProductConditionRule {}
}
