<?php

namespace craft\commerce\base;

/** @deprecated use {@see \CraftCms\Commerce\Base\Zone} */
class_alias(\CraftCms\Commerce\Base\Zone::class, 'craft\commerce\base\Zone');

/** @phpstan-ignore-next-line */
if (false) {
    abstract class Zone extends \CraftCms\Commerce\Base\Zone {}
}
