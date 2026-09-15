<?php

namespace craft\commerce\base;

/** @deprecated use {@see \CraftCms\Commerce\Purchasable\Elements\Purchasable} */
class_alias(\CraftCms\Commerce\Purchasable\Elements\Purchasable::class, 'craft\commerce\base\Purchasable');

/** @phpstan-ignore-next-line */
if (false) {
    abstract class Purchasable extends \CraftCms\Commerce\Purchasable\Elements\Purchasable {}
}
