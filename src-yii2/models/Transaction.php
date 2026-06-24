<?php

namespace craft\commerce\models;

/** @deprecated use {@see \CraftCms\Commerce\Payment\Models\Transaction} */
class_alias(\CraftCms\Commerce\Payment\Models\Transaction::class, 'craft\commerce\models\Transaction');

/** @phpstan-ignore-next-line */
if (false) {
    class Transaction extends \CraftCms\Commerce\Payment\Models\Transaction {}
}
