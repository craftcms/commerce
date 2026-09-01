<?php

namespace craft\commerce\models;

/** @deprecated use {@see \CraftCms\Commerce\Catalog\Data\CatalogPricing} */
class_alias(\CraftCms\Commerce\Catalog\Data\CatalogPricing::class, 'craft\commerce\models\CatalogPricing');

/** @phpstan-ignore-next-line */
if (false) {
    class CatalogPricing extends \CraftCms\Commerce\Catalog\Data\CatalogPricing {}
}
