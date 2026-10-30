<?php

namespace craft\commerce\elements;

/** @deprecated use {@see \CraftCms\Commerce\Purchasable\Elements\Donation} */
class_alias(\CraftCms\Commerce\Purchasable\Elements\Donation::class, 'craft\commerce\elements\Donation');

/** @phpstan-ignore-next-line */
if (false) {
    class Donation extends \CraftCms\Commerce\Purchasable\Elements\Donation {}
}
