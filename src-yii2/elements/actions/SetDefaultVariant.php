<?php

namespace craft\commerce\elements\actions;

/** @deprecated use {@see \CraftCms\Commerce\Product\Variant\Actions\SetDefaultVariant} */
class_alias(\CraftCms\Commerce\Product\Variant\Actions\SetDefaultVariant::class, 'craft\commerce\elements\actions\SetDefaultVariant');

/** @phpstan-ignore-next-line */
if (false) {
    class SetDefaultVariant extends \CraftCms\Commerce\Product\Variant\Actions\SetDefaultVariant {}
}
