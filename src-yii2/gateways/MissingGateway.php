<?php

namespace craft\commerce\gateways;

/** @deprecated use {@see \CraftCms\Commerce\Payment\Gateway\Types\MissingGateway} */
class_alias(\CraftCms\Commerce\Payment\Gateway\Types\MissingGateway::class, 'craft\commerce\gateways\MissingGateway');

/** @phpstan-ignore-next-line */
if (false) {
    class MissingGateway extends \CraftCms\Commerce\Payment\Gateway\Types\MissingGateway {}
}
