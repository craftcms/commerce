<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Tax\Events;

use CraftCms\Commerce\Tax\Contracts\TaxEngineInterface;

class TaxEngineEvent
{
    public function __construct(
        public TaxEngineInterface $engine,
    ) {
    }
}
