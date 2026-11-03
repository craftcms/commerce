<?php

namespace craft\commerce\events;

use yii\base\Event;

/**
 * @deprecated 6.0.0 Use {@see \CraftCms\Commerce\Tax\Events\TaxIdValidatorsResolving} instead.
 */
class TaxIdValidatorsEvent extends Event
{
    /**
     * @var array The tax ID validators
     */
    public array $validators = [];
}
