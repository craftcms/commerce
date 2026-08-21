<?php

namespace craft\commerce\helpers;

/** @deprecated use {@see \CraftCms\Commerce\Helpers\Purchasable} */
class_alias(\CraftCms\Commerce\Helpers\Purchasable::class, 'craft\commerce\helpers\Purchasable');

/** @phpstan-ignore-next-line */
if (false) {
    class Purchasable extends \CraftCms\Commerce\Helpers\Purchasable {}
}
