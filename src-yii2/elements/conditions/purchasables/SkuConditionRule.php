<?php

namespace craft\commerce\elements\conditions\purchasables;

/** @deprecated use {@see \CraftCms\Commerce\Purchasable\Conditions\SkuConditionRule} */
class_alias(\CraftCms\Commerce\Purchasable\Conditions\SkuConditionRule::class, 'craft\commerce\elements\conditions\purchasables\SkuConditionRule');

/** @phpstan-ignore-next-line */
if (false) {
    class SkuConditionRule extends \CraftCms\Commerce\Purchasable\Conditions\SkuConditionRule {}
}
