<?php

namespace craft\commerce\models\responses;

/** @deprecated use {@see \CraftCms\Commerce\Payment\Gateway\Responses\Dummy} */
class_alias(\CraftCms\Commerce\Payment\Gateway\Responses\Dummy::class, 'craft\commerce\models\responses\Dummy');

/** @phpstan-ignore-next-line */
if (false) {
    class Dummy extends \CraftCms\Commerce\Payment\Gateway\Responses\Dummy {}
}
