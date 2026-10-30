<?php

namespace craft\commerce\errors;

/** @deprecated use {@see \CraftCms\Commerce\Payment\Gateway\Exceptions\GatewayException} */
class_alias(\CraftCms\Commerce\Payment\Gateway\Exceptions\GatewayException::class, 'craft\commerce\errors\GatewayException');

/** @phpstan-ignore-next-line */
if (false) {
    class GatewayException extends \CraftCms\Commerce\Payment\Gateway\Exceptions\GatewayException {}
}
