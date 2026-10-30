<?php

namespace craft\commerce\adjusters;

/** @deprecated use {@see \CraftCms\Commerce\Order\Adjuster\Discount} */
class_alias(\CraftCms\Commerce\Order\Adjuster\Discount::class, 'craft\commerce\adjusters\Discount');

/** @phpstan-ignore-next-line */
if (false) {
    class Discount extends \CraftCms\Commerce\Order\Adjuster\Discount {}
}
