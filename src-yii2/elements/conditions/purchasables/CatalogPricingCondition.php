<?php

namespace craft\commerce\elements\conditions\purchasables;

/** @deprecated use {@see \CraftCms\Commerce\CatalogPricing\Conditions\CatalogPricingCondition} */
class_alias(\CraftCms\Commerce\CatalogPricing\Conditions\CatalogPricingCondition::class, 'craft\commerce\elements\conditions\purchasables\CatalogPricingCondition');

/** @phpstan-ignore-next-line */
if (false) {
    class CatalogPricingCondition extends \CraftCms\Commerce\CatalogPricing\Conditions\CatalogPricingCondition {}
}
