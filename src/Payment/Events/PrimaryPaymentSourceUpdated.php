<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Payment\Events;

use CraftCms\Cms\User\Elements\User;

/**
 * Dispatched after a customer's primary payment source changes.
 */
class PrimaryPaymentSourceUpdated
{
    public function __construct(
        public User $customer,
        public ?int $previousPrimaryPaymentSourceId = null,
        public ?int $newPrimaryPaymentSourceId = null,
    ) {
    }
}
