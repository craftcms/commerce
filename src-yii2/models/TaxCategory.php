<?php

namespace craft\commerce\models;

/** @deprecated use {@see \CraftCms\Commerce\Tax\Data\TaxCategory} */
class_alias(\CraftCms\Commerce\Tax\Data\TaxCategory::class, 'craft\commerce\models\TaxCategory');

/** @phpstan-ignore-next-line */
if (false) {
    class TaxCategory extends \CraftCms\Commerce\Tax\Data\TaxCategory {}
}
