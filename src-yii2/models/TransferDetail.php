<?php

namespace craft\commerce\models;

/** @deprecated use {@see \CraftCms\Commerce\Transfer\Models\TransferDetail} */
class_alias(\CraftCms\Commerce\Transfer\Models\TransferDetail::class, 'craft\commerce\models\TransferDetail');

/** @phpstan-ignore-next-line */
if (false) {
    class TransferDetail extends \CraftCms\Commerce\Transfer\Models\TransferDetail {}
}
