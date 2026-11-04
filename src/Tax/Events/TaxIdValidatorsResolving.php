<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Tax\Events;

/**
 * Dispatched when resolving the validators used for tax IDs. Listeners may add `CraftCms\Commerce\Tax\Contracts\TaxIdValidatorInterface` validators to `$validators`, or replace them.
 */
class TaxIdValidatorsResolving
{
    public function __construct(
        public array $validators = [],
    ) {
    }
}
