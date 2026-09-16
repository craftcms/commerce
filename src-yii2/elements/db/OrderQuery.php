<?php

namespace craft\commerce\elements\db;

/** @deprecated use {@see \CraftCms\Commerce\Order\Queries\OrderQuery} */
class_alias(\CraftCms\Commerce\Order\Queries\OrderQuery::class, 'craft\commerce\elements\db\OrderQuery');

/** @phpstan-ignore-next-line */
if (false) {
    class OrderQuery extends \CraftCms\Commerce\Order\Queries\OrderQuery {}
}
