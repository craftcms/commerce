<?php

namespace craft\commerce\models;

/** @deprecated use {@see \CraftCms\Commerce\Transfer\Data\TransferDetail} */
class_alias(\CraftCms\Commerce\Transfer\Data\TransferDetail::class, 'craft\commerce\models\TransferDetail');

/** @phpstan-ignore-next-line */
if (false) {
    class TransferDetail extends \CraftCms\Commerce\Transfer\Data\TransferDetail {}
}
