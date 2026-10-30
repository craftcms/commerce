<?php

namespace craft\commerce\adjusters;

/** @deprecated use {@see \CraftCms\Commerce\Order\Adjuster\Tax} */
class_alias(\CraftCms\Commerce\Order\Adjuster\Tax::class, 'craft\commerce\adjusters\Tax');

/** @phpstan-ignore-next-line */
if (false) {
    class Tax extends \CraftCms\Commerce\Order\Adjuster\Tax {}
}
