<?php

namespace craft\commerce\gateways;

/** @deprecated use {@see \CraftCms\Commerce\Payment\Gateway\Types\Dummy} */
class_alias(\CraftCms\Commerce\Payment\Gateway\Types\Dummy::class, 'craft\commerce\gateways\Dummy');

/** @phpstan-ignore-next-line */
if (false) {
    class Dummy extends \CraftCms\Commerce\Payment\Gateway\Types\Dummy {}
}
