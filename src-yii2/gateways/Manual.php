<?php

namespace craft\commerce\gateways;

/** @deprecated use {@see \CraftCms\Commerce\Payment\Gateway\Types\Manual} */
class_alias(\CraftCms\Commerce\Payment\Gateway\Types\Manual::class, 'craft\commerce\gateways\Manual');

/** @phpstan-ignore-next-line */
if (false) {
    class Manual extends \CraftCms\Commerce\Payment\Gateway\Types\Manual {}
}
