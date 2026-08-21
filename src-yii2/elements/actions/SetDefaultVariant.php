<?php

namespace craft\commerce\elements\actions;

/** @deprecated use {@see \CraftCms\Commerce\Catalog\Actions\SetDefaultVariant} */
class_alias(\CraftCms\Commerce\Catalog\Actions\SetDefaultVariant::class, 'craft\commerce\elements\actions\SetDefaultVariant');

/** @phpstan-ignore-next-line */
if (false) {
    class SetDefaultVariant extends \CraftCms\Commerce\Catalog\Actions\SetDefaultVariant {}
}
