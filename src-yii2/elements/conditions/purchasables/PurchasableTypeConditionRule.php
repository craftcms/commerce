<?php

namespace craft\commerce\elements\conditions\purchasables;

/** @deprecated use {@see \CraftCms\Commerce\Purchasable\Conditions\PurchasableTypeConditionRule} */
class_alias(\CraftCms\Commerce\Purchasable\Conditions\PurchasableTypeConditionRule::class, 'craft\commerce\elements\conditions\purchasables\PurchasableTypeConditionRule');

/** @phpstan-ignore-next-line */
if (false) {
    class PurchasableTypeConditionRule extends \CraftCms\Commerce\Purchasable\Conditions\PurchasableTypeConditionRule {}
}
