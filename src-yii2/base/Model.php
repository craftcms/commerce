<?php

namespace craft\commerce\base;

/** @deprecated use {@see \CraftCms\Cms\Component\Component} */
class_alias(\CraftCms\Cms\Component\Component::class, 'craft\commerce\base\Model');

/** @phpstan-ignore-next-line */
if (false) {
    class Model extends \CraftCms\Cms\Component\Component {}
}
