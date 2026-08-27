<?php

namespace craft\commerce\models;

/** @deprecated use {@see \CraftCms\Commerce\Tax\Data\TaxRate} */
class_alias(\CraftCms\Commerce\Tax\Data\TaxRate::class, 'craft\commerce\models\TaxRate');

/** @phpstan-ignore-next-line */
if (false) {
    class TaxRate extends \CraftCms\Commerce\Tax\Data\TaxRate {}
}
