<?php

namespace craft\commerce\engines;

/** @deprecated use {@see \CraftCms\Commerce\Tax\Engines\Tax} */
class_alias(\CraftCms\Commerce\Tax\Engines\Tax::class, 'craft\commerce\engines\Tax');

/** @phpstan-ignore-next-line */
if (false) {
    class Tax extends \CraftCms\Commerce\Tax\Engines\Tax {}
}
