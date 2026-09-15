<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Tax\Events;

use CraftCms\Commerce\Tax\Contracts\TaxEngineInterface;
use yii\base\Event;

class TaxEngineEvent extends Event
{
    public function __construct(
        public TaxEngineInterface $engine,
    ) {
    }
}
