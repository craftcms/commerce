<?php

namespace craft\commerce\events;

use CraftCms\Commerce\Tax\Contracts\TaxEngineInterface;
use yii\base\Event;

/**
 * @deprecated 6.0.0 Use {@see \CraftCms\Commerce\Tax\Events\TaxEngineResolving} instead.
 */
class TaxEngineEvent extends Event
{
    /**
     * @var TaxEngineInterface The tax engine
     */
    public TaxEngineInterface $engine;
}
