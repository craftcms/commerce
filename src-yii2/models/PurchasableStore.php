<?php

namespace craft\commerce\models;

/** @deprecated use {@see \CraftCms\Commerce\Purchasable\Data\PurchasableStore} */
class_alias(\CraftCms\Commerce\Purchasable\Data\PurchasableStore::class, 'craft\commerce\models\PurchasableStore');

/** @phpstan-ignore-next-line */
if (false) {
    class PurchasableStore extends \CraftCms\Commerce\Purchasable\Data\PurchasableStore {}
}
