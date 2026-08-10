<?php

namespace craft\commerce\elements\db;

/** @deprecated use {@see \CraftCms\Commerce\Purchasable\Queries\DonationQuery} */
class_alias(\CraftCms\Commerce\Purchasable\Queries\DonationQuery::class, 'craft\commerce\elements\db\DonationQuery');

/** @phpstan-ignore-next-line */
if (false) {
    class DonationQuery extends \CraftCms\Commerce\Purchasable\Queries\DonationQuery {}
}
