<?php

namespace craft\commerce\services;

/** @deprecated use {@see \CraftCms\Commerce\Transfer\Transfers} */
class_alias(\CraftCms\Commerce\Transfer\Transfers::class, 'craft\commerce\services\Transfers');

/** @phpstan-ignore-next-line */
if (false) {
    class Transfers extends \CraftCms\Commerce\Transfer\Transfers {}
}
