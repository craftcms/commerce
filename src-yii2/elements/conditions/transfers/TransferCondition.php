<?php

namespace craft\commerce\elements\conditions\transfers;

/** @deprecated use {@see \CraftCms\Commerce\Transfer\Conditions\TransferCondition} */
class_alias(\CraftCms\Commerce\Transfer\Conditions\TransferCondition::class, 'craft\commerce\elements\conditions\transfers\TransferCondition');

/** @phpstan-ignore-next-line */
if (false) {
    class TransferCondition extends \CraftCms\Commerce\Transfer\Conditions\TransferCondition {}
}
