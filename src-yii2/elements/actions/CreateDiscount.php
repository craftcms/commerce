<?php

namespace craft\commerce\elements\actions;

/** @deprecated use {@see \CraftCms\Commerce\Promotion\Actions\CreateDiscount} */
class_alias(\CraftCms\Commerce\Promotion\Actions\CreateDiscount::class, 'craft\commerce\elements\actions\CreateDiscount');

/** @phpstan-ignore-next-line */
if (false) {
    class CreateDiscount extends \CraftCms\Commerce\Promotion\Actions\CreateDiscount {}
}
