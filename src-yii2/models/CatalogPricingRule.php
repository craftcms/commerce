<?php

namespace craft\commerce\models;

/** @deprecated use {@see \CraftCms\Commerce\Catalog\Data\CatalogPricingRule} */
class_alias(\CraftCms\Commerce\Catalog\Data\CatalogPricingRule::class, 'craft\commerce\models\CatalogPricingRule');

/** @phpstan-ignore-next-line */
if (false) {
    class CatalogPricingRule extends \CraftCms\Commerce\Catalog\Data\CatalogPricingRule {}
}
