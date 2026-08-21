<?php

namespace craft\commerce\models;

/** @deprecated use {@see \CraftCms\Commerce\Tax\Models\TaxAddressZone} */
class_alias(\CraftCms\Commerce\Tax\Models\TaxAddressZone::class, 'craft\commerce\models\TaxAddressZone');

/** @phpstan-ignore-next-line */
if (false) {
    class TaxAddressZone extends \CraftCms\Commerce\Tax\Models\TaxAddressZone {}
}
