<?php

namespace craft\commerce\models;

/** @deprecated use {@see \CraftCms\Commerce\Shipping\Models\ShippingRuleCategory} */
class_alias(\CraftCms\Commerce\Shipping\Models\ShippingRuleCategory::class, 'craft\commerce\models\ShippingRuleCategory');

/** @phpstan-ignore-next-line */
if (false) {
    class ShippingRuleCategory extends \CraftCms\Commerce\Shipping\Models\ShippingRuleCategory {}
}
