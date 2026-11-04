<?php

namespace craft\commerce\elements\conditions\purchasables;

/** @deprecated use {@see \CraftCms\Commerce\Purchasable\Conditions\CatalogPricingRulePurchasableEntryConditionRule} */
class_alias(\CraftCms\Commerce\Purchasable\Conditions\CatalogPricingRulePurchasableEntryConditionRule::class, 'craft\commerce\elements\conditions\purchasables\CatalogPricingRulePurchasableCategoryConditionRule');

/** @phpstan-ignore-next-line */
if (false) {
    class CatalogPricingRulePurchasableCategoryConditionRule extends \CraftCms\Commerce\Purchasable\Conditions\CatalogPricingRulePurchasableEntryConditionRule
    {
    }
}
