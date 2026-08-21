<?php

namespace craft\commerce\models;

/** @deprecated use {@see \CraftCms\Commerce\Tax\Models\TaxCategory} */
class_alias(\CraftCms\Commerce\Tax\Models\TaxCategory::class, 'craft\commerce\models\TaxCategory');

/** @phpstan-ignore-next-line */
if (false) {
    class TaxCategory extends \CraftCms\Commerce\Tax\Models\TaxCategory {}
}
