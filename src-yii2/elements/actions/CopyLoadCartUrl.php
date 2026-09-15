<?php

namespace craft\commerce\elements\actions;

/** @deprecated use {@see \CraftCms\Commerce\Order\Actions\CopyLoadCartUrl} */
class_alias(\CraftCms\Commerce\Order\Actions\CopyLoadCartUrl::class, 'craft\commerce\elements\actions\CopyLoadCartUrl');

/** @phpstan-ignore-next-line */
if (false) {
    class CopyLoadCartUrl extends \CraftCms\Commerce\Order\Actions\CopyLoadCartUrl {}
}
