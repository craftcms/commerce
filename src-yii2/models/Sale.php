<?php

namespace craft\commerce\models;

/** @deprecated use {@see \CraftCms\Commerce\Promotion\Data\Sale} */
class_alias(\CraftCms\Commerce\Promotion\Data\Sale::class, 'craft\commerce\models\Sale');

/** @phpstan-ignore-next-line */
if (false) {
    class Sale extends \CraftCms\Commerce\Promotion\Data\Sale {}
}
