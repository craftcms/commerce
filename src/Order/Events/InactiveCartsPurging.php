<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Events;

use CraftCms\Cms\Shared\Concerns\ValidatableEvent;
use Illuminate\Database\Query\Builder;

/**
 * Dispatched before inactive carts are purged. Listeners may narrow `$inactiveCartsQuery`, which selects the IDs of the carts to purge, or set `$isValid` to `false` to skip the purge.
 */
class InactiveCartsPurging
{
    use ValidatableEvent;

    public function __construct(
        public Builder $inactiveCartsQuery,
    ) {
    }
}
