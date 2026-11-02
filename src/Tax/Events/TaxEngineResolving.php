<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Tax\Events;

use CraftCms\Commerce\Tax\Contracts\TaxEngineInterface;

/**
 * Dispatched when resolving the tax engine. Listeners may replace `$engine`.
 */
class TaxEngineResolving
{
    public function __construct(
        public TaxEngineInterface $engine,
    ) {
    }
}
