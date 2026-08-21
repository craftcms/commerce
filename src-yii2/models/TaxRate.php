<?php

namespace craft\commerce\models;

/** @deprecated use {@see \CraftCms\Commerce\Tax\Models\TaxRate} */
class_alias(\CraftCms\Commerce\Tax\Models\TaxRate::class, 'craft\commerce\models\TaxRate');

/** @phpstan-ignore-next-line */
if (false) {
    class TaxRate extends \CraftCms\Commerce\Tax\Models\TaxRate {}
}
