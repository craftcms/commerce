<?php

namespace craft\commerce\helpers;

/** @deprecated use {@see \CraftCms\Commerce\Helpers\Currency} */
class_alias(\CraftCms\Commerce\Helpers\Currency::class, 'craft\commerce\helpers\Currency');

/** @phpstan-ignore-next-line */
if (false) {
    class Currency extends \CraftCms\Commerce\Helpers\Currency {}
}
