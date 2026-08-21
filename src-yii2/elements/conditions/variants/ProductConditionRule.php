<?php

namespace craft\commerce\elements\conditions\variants;

/** @deprecated use {@see \CraftCms\Commerce\Catalog\Conditions\VariantProductConditionRule} */
class_alias(\CraftCms\Commerce\Catalog\Conditions\VariantProductConditionRule::class, 'craft\commerce\elements\conditions\variants\ProductConditionRule');

/** @phpstan-ignore-next-line */
if (false) {
    class ProductConditionRule extends \CraftCms\Commerce\Catalog\Conditions\VariantProductConditionRule {}
}
