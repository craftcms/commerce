<?php

namespace craft\commerce;

/** @deprecated use {@see \CraftCms\Commerce\Plugin} */
class_alias(\CraftCms\Commerce\Plugin::class, 'craft\commerce\Plugin');

/** @phpstan-ignore-next-line */
if (false) {
    class Plugin extends \CraftCms\Commerce\Plugin {}
}
