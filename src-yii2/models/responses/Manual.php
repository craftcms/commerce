<?php

namespace craft\commerce\models\responses;

/** @deprecated use {@see \CraftCms\Commerce\Payment\Gateway\Responses\Manual} */
class_alias(\CraftCms\Commerce\Payment\Gateway\Responses\Manual::class, 'craft\commerce\models\responses\Manual');

/** @phpstan-ignore-next-line */
if (false) {
    class Manual extends \CraftCms\Commerce\Payment\Gateway\Responses\Manual {}
}
