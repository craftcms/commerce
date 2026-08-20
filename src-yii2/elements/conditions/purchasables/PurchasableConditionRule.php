<?php

namespace craft\commerce\elements\conditions\purchasables;

/** @deprecated use {@see \CraftCms\Commerce\Purchasable\Conditions\PurchasableConditionRule} */
class_alias(\CraftCms\Commerce\Purchasable\Conditions\PurchasableConditionRule::class, 'craft\commerce\elements\conditions\purchasables\PurchasableConditionRule');

/** @phpstan-ignore-next-line */
if (false) {
    class PurchasableConditionRule extends \CraftCms\Commerce\Purchasable\Conditions\PurchasableConditionRule {}
}
