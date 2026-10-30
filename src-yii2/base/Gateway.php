<?php

namespace craft\commerce\base;

/** @deprecated use {@see \CraftCms\Commerce\Payment\Gateway\Gateway} */
class_alias(\CraftCms\Commerce\Payment\Gateway\Gateway::class, 'craft\commerce\base\Gateway');

/** @phpstan-ignore-next-line */
if (false) {
    class Gateway extends \CraftCms\Commerce\Payment\Gateway\Gateway {}
}
