<?php

namespace craft\commerce\models;

/** @deprecated use {@see \CraftCms\Commerce\Promotion\Data\Coupon} */
class_alias(\CraftCms\Commerce\Promotion\Data\Coupon::class, 'craft\commerce\models\Coupon');

/** @phpstan-ignore-next-line */
if (false) {
    class Coupon extends \CraftCms\Commerce\Promotion\Data\Coupon {}
}
