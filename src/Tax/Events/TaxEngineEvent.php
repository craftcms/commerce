<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Tax\Events;

use craft\commerce\base\TaxEngineInterface;

class TaxEngineEvent
{
    public function __construct(
        public TaxEngineInterface $engine,
    ) {
    }
}
