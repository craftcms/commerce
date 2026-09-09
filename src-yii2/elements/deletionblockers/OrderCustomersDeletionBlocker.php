<?php

namespace craft\commerce\elements\deletionblockers;

/** @deprecated use {@see \CraftCms\Commerce\Order\DeletionBlockers\OrderCustomersDeletionBlocker} */
class_alias(\CraftCms\Commerce\Order\DeletionBlockers\OrderCustomersDeletionBlocker::class, 'craft\commerce\elements\deletionblockers\OrderCustomersDeletionBlocker');

/** @phpstan-ignore-next-line */
if (false) {
    class OrderCustomersDeletionBlocker extends \CraftCms\Commerce\Order\DeletionBlockers\OrderCustomersDeletionBlocker {}
}
