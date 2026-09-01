<?php

namespace craft\commerce\models;

/** @deprecated use {@see \CraftCms\Commerce\Promotion\Data\Discount} */
class_alias(\CraftCms\Commerce\Promotion\Data\Discount::class, 'craft\commerce\models\Discount');

/** @phpstan-ignore-next-line */
if (false) {
    class Discount extends \CraftCms\Commerce\Promotion\Data\Discount {}
}
