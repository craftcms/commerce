<?php

namespace craft\commerce\elements\conditions\variants;

/** @deprecated use {@see \CraftCms\Commerce\Catalog\Conditions\VariantCondition} */
class_alias(\CraftCms\Commerce\Catalog\Conditions\VariantCondition::class, 'craft\commerce\elements\conditions\variants\VariantCondition');

/** @phpstan-ignore-next-line */
if (false) {
    class VariantCondition extends \CraftCms\Commerce\Catalog\Conditions\VariantCondition {}
}
