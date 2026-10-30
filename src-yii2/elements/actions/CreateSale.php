<?php

namespace craft\commerce\elements\actions;

/** @deprecated use {@see \CraftCms\Commerce\Promotion\Actions\CreateSale} */
class_alias(\CraftCms\Commerce\Promotion\Actions\CreateSale::class, 'craft\commerce\elements\actions\CreateSale');

/** @phpstan-ignore-next-line */
if (false) {
    class CreateSale extends \CraftCms\Commerce\Promotion\Actions\CreateSale {}
}
