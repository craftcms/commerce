<?php

namespace craft\commerce\models;

/** @deprecated use {@see \CraftCms\Commerce\Tax\Data\TaxAddressZone} */
class_alias(\CraftCms\Commerce\Tax\Data\TaxAddressZone::class, 'craft\commerce\models\TaxAddressZone');

/** @phpstan-ignore-next-line */
if (false) {
    class TaxAddressZone extends \CraftCms\Commerce\Tax\Data\TaxAddressZone {}
}
