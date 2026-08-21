<?php

namespace craft\commerce\models;

/** @deprecated use {@see \CraftCms\Commerce\Promotion\Models\Discount} */
class_alias(\CraftCms\Commerce\Promotion\Models\Discount::class, 'craft\commerce\models\Discount');

/** @phpstan-ignore-next-line */
if (false) {
    class Discount extends \CraftCms\Commerce\Promotion\Models\Discount {}
}
