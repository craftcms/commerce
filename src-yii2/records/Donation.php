<?php

namespace craft\commerce\records;

/** @deprecated use {@see \CraftCms\Commerce\Purchasable\Models\Donation} */
class_alias(\CraftCms\Commerce\Purchasable\Models\Donation::class, 'craft\commerce\records\Donation');

/** @phpstan-ignore-next-line */
if (false) {
    class Donation extends \CraftCms\Commerce\Purchasable\Models\Donation {}
}
