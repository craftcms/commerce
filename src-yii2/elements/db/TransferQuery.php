<?php

namespace craft\commerce\elements\db;

/** @deprecated use {@see \CraftCms\Commerce\Transfer\Queries\TransferQuery} */
class_alias(\CraftCms\Commerce\Transfer\Queries\TransferQuery::class, 'craft\commerce\elements\db\TransferQuery');

/** @phpstan-ignore-next-line */
if (false) {
    class TransferQuery extends \CraftCms\Commerce\Transfer\Queries\TransferQuery {}
}
