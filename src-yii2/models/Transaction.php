<?php

namespace craft\commerce\models;

/** @deprecated use {@see \CraftCms\Commerce\Payment\Data\Transaction} */
class_alias(\CraftCms\Commerce\Payment\Data\Transaction::class, 'craft\commerce\models\Transaction');

/** @phpstan-ignore-next-line */
if (false) {
    class Transaction extends \CraftCms\Commerce\Payment\Data\Transaction {}
}
